<?php
/**
 * Plugin Name: Dynamic Display Name Manager
 * Plugin URI: https://github.com/yourname/dynamic-display-name-manager
 * Description: Configure user display names using selected user fields (username, email, first name, last name, website, role) with batch processing.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: dynamic-display-name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class DynamicDisplayNameManager {
    
    private $option_name = 'ddnm_settings';
    private $batch_size = 50;
    private $processing_user = false; // Flag to prevent infinite loops
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_ddnm_process_batch', array($this, 'ajax_process_batch'));
        add_action('user_register', array($this, 'set_new_user_display_name'));
        add_action('profile_update', array($this, 'update_user_display_name'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Dynamic Display Name Manager',
            'Display Name Manager',
            'manage_options',
            'dynamic-display-name-manager',
            array($this, 'admin_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_dynamic-display-name-manager') {
            return;
        }
        
        wp_enqueue_script('ddnm-admin', plugin_dir_url(__FILE__) . 'admin.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('ddnm-admin', plugin_dir_url(__FILE__) . 'admin.css', array(), '1.0.0');
        
        wp_localize_script('ddnm-admin', 'ddnm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ddnm_nonce'),
            'batch_size' => $this->batch_size
        ));
    }
    
    public function admin_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $settings = get_option($this->option_name, array());
        $total_users = count_users()['total_users'];
        ?>
        <div class="wrap">
            <h1>Dynamic Display Name Manager</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('ddnm_save_settings', 'ddnm_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Display Name Fields</th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span>Display Name Fields</span></legend>
                                <label><input type="checkbox" name="ddnm_fields[]" value="username" <?php checked(in_array('username', $settings)); ?>> Username</label><br>
                                <label><input type="checkbox" name="ddnm_fields[]" value="email" <?php checked(in_array('email', $settings)); ?>> Email</label><br>
                                <label><input type="checkbox" name="ddnm_fields[]" value="first_name" <?php checked(in_array('first_name', $settings)); ?>> First Name</label><br>
                                <label><input type="checkbox" name="ddnm_fields[]" value="last_name" <?php checked(in_array('last_name', $settings)); ?>> Last Name</label><br>
                                <label><input type="checkbox" name="ddnm_fields[]" value="website" <?php checked(in_array('website', $settings)); ?>> Website</label><br>
                                <label><input type="checkbox" name="ddnm_fields[]" value="role" <?php checked(in_array('role', $settings)); ?>> Role</label>
                            </fieldset>
                            <p class="description">Select which fields to include in the display name (separated by spaces).</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Settings'); ?>
            </form>
            
            <?php if (!empty($settings)): ?>
            <hr>
            <h2>Update Existing Users</h2>
            <p>Apply the current display name format to all existing users (<?php echo $total_users; ?> users).</p>
            <button type="button" id="start-batch-process" class="button button-primary">Start Batch Update</button>
            
            <div id="batch-progress" style="display: none;">
                <p>Processing users... <span id="progress-text">0 / <?php echo $total_users; ?></span></p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%;"></div>
                </div>
            </div>
            
            <div id="batch-complete" style="display: none;">
                <p><strong>Batch update completed successfully!</strong></p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function save_settings() {
        if (!wp_verify_nonce($_POST['ddnm_nonce'], 'ddnm_save_settings')) {
            wp_die('Security check failed');
        }
        
        $fields = isset($_POST['ddnm_fields']) ? array_map('sanitize_text_field', $_POST['ddnm_fields']) : array();
        update_option($this->option_name, $fields);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
        });
    }
    
    public function ajax_process_batch() {
        // Log the request for debugging
        error_log('DDNM: AJAX request received - ' . print_r($_POST, true));
        
        // Check nonce and capabilities
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, 'ddnm_nonce')) {
            error_log('DDNM: Nonce verification failed');
            wp_send_json_error('Security check failed - Invalid nonce');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            error_log('DDNM: User capability check failed');
            wp_send_json_error('Security check failed - Insufficient permissions');
            return;
        }
        
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $settings = get_option($this->option_name, array());
        
        error_log('DDNM: Settings - ' . print_r($settings, true));
        
        if (empty($settings)) {
            error_log('DDNM: No fields selected');
            wp_send_json_error('No fields selected');
            return;
        }
        
        try {
            $users = get_users(array(
                'number' => $this->batch_size,
                'offset' => $offset,
                'fields' => 'all'
            ));
            
            error_log('DDNM: Found ' . count($users) . ' users at offset ' . $offset);
            
            $processed = 0;
            foreach ($users as $user) {
                if ($this->update_user_display_name_by_settings($user->ID, $settings, true)) {
                    $processed++;
                }
            }
            
            error_log('DDNM: Processed ' . $processed . ' users successfully');
            
            wp_send_json_success(array(
                'processed' => $processed,
                'has_more' => count($users) === $this->batch_size
            ));
            
        } catch (Exception $e) {
            error_log('DDNM: Exception - ' . $e->getMessage());
            wp_send_json_error('Processing error: ' . $e->getMessage());
        }
    }
    
    public function set_new_user_display_name($user_id) {
        $settings = get_option($this->option_name, array());
        if (!empty($settings)) {
            $this->update_user_display_name_by_settings($user_id, $settings, true);
        }
    }
    
    public function update_user_display_name($user_id) {
        // Prevent infinite loops
        if ($this->processing_user === $user_id) {
            return;
        }
        
        $settings = get_option($this->option_name, array());
        if (!empty($settings)) {
            $this->update_user_display_name_by_settings($user_id, $settings);
        }
    }
    
    private function update_user_display_name_by_settings($user_id, $settings, $force_update = false) {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        $display_parts = array();
        
        foreach ($settings as $field) {
            $value = '';
            
            switch ($field) {
                case 'username':
                    $value = $user->user_login;
                    break;
                case 'email':
                    $value = $user->user_email;
                    break;
                case 'first_name':
                    $value = get_user_meta($user_id, 'first_name', true);
                    break;
                case 'last_name':
                    $value = get_user_meta($user_id, 'last_name', true);
                    break;
                case 'website':
                    $value = $user->user_url;
                    break;
                case 'role':
                    $roles = $user->roles;
                    $value = !empty($roles) ? ucfirst($roles[0]) : '';
                    break;
            }
            
            if (!empty($value)) {
                $display_parts[] = sanitize_text_field($value);
            }
        }
        
        $new_display_name = implode(' ', $display_parts);
        
        // Only update if the display name is different or we're forcing an update
        if (!empty($new_display_name) && ($force_update || $user->display_name !== $new_display_name)) {
            // Prevent infinite loop by setting the processing flag
            $this->processing_user = $user_id;
            
            // Temporarily remove the profile_update hook to prevent infinite loop
            remove_action('profile_update', array($this, 'update_user_display_name'));
            
            $result = wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $new_display_name
            ));
            
            // Re-add the profile_update hook
            add_action('profile_update', array($this, 'update_user_display_name'));
            
            // Reset the processing flag
            $this->processing_user = false;
            
            return !is_wp_error($result);
        }
        
        return false;
    }
}

// Initialize the plugin
new DynamicDisplayNameManager();
