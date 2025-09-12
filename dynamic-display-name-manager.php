<?php
/**
 * Plugin Name: Dynamic Display Name Manager
 * Plugin URI: https://github.com/S4hk/dynamic-display-name-manager
 * Description: Configure user display names using selected user fields (username, email, first name, last name, website, role) with batch processing.
 * Version: 1.0.0
 * Author: s4hk
 * Author URI: https://github.com/s4hk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dynamic-display-name-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'DDNM_VERSION', '1.0.0' );
define( 'DDNM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DDNM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DDNM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

class DynamicDisplayNameManager {

	private $option_name    = 'ddnm_settings';
	private $batch_size     = 50;
	private $processing_user = false;

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	public function activate() {
		// Add default options if they don't exist
		if ( false === get_option( $this->option_name ) ) {
			add_option( $this->option_name, array() );
		}

		// Check minimum requirements
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( DDNM_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'This plugin requires PHP 7.4 or higher.', 'dynamic-display-name-manager' ),
				esc_html__( 'Plugin Activation Error', 'dynamic-display-name-manager' ),
				array( 'back_link' => true )
			);
		}
	}

	public function deactivate() {
		// Clean up if needed
		delete_transient( 'ddnm_batch_processing' );
	}

	public function init() {
		// Check user capabilities early
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
			add_action( 'wp_ajax_ddnm_process_batch', array( $this, 'ajax_process_batch' ) );
			add_filter( 'plugin_action_links_' . DDNM_PLUGIN_BASENAME, array( $this, 'add_plugin_action_links' ) );
			add_filter( 'plugin_row_meta', array( $this, 'add_plugin_row_meta' ), 10, 2 );
		}

		// Hooks for all contexts
		add_action( 'user_register', array( $this, 'set_new_user_display_name' ) );
		add_action( 'profile_update', array( $this, 'update_user_display_name' ) );
	}

	public function add_admin_menu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_management_page(
			esc_html__( 'Dynamic Display Name Manager', 'dynamic-display-name-manager' ),
			esc_html__( 'Display Name Manager', 'dynamic-display-name-manager' ),
			'manage_options',
			'dynamic-display-name-manager',
			array( $this, 'admin_page' )
		);
	}

	public function add_plugin_action_links( $links ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}

		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'tools.php?page=dynamic-display-name-manager' ) ),
			esc_html__( 'Settings', 'dynamic-display-name-manager' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function add_plugin_row_meta( $links, $file ) {
		if ( $file === DDNM_PLUGIN_BASENAME && current_user_can( 'manage_options' ) ) {
			$row_meta = array(
				'configure' => sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'tools.php?page=dynamic-display-name-manager' ) ),
					esc_html__( 'Configure', 'dynamic-display-name-manager' )
				),
			);
			return array_merge( $links, $row_meta );
		}
		return $links;
	}

	public function enqueue_admin_scripts( $hook ) {
		if ( 'tools_page_dynamic-display-name-manager' !== $hook ) {
			return;
		}

		$script_version = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : DDNM_VERSION;

		wp_enqueue_script(
			'ddnm-admin',
			DDNM_PLUGIN_URL . 'admin.js',
			array( 'jquery' ),
			$script_version,
			true
		);

		wp_enqueue_style(
			'ddnm-admin',
			DDNM_PLUGIN_URL . 'admin.css',
			array(),
			$script_version
		);

		wp_localize_script(
			'ddnm-admin',
			'ddnm_ajax',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'ddnm_nonce' ),
				'batch_size' => $this->batch_size,
				'strings'    => array(
					'unknown_error' => esc_html__( 'Unknown error occurred', 'dynamic-display-name-manager' ),
					'server_error'  => esc_html__( 'Server error', 'dynamic-display-name-manager' ),
					'error_prefix'  => esc_html__( 'Error', 'dynamic-display-name-manager' ),
				),
			)
		);
	}

	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'dynamic-display-name-manager' ) );
		}

		// Only process form if nonce is present and valid.
		// We DO NOT look at other $_POST fields before verifying the nonce to satisfy PHPCS.
		if ( isset( $_POST['ddnm_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ddnm_nonce'] ) ), 'ddnm_save_settings' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified inline.
			$this->save_settings();
		} elseif ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['ddnm_nonce'] ) ) {
			// POSTed but nonce invalid
			add_action(
				'admin_notices',
				function() {
					printf(
						'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
						esc_html__( 'Security check failed', 'dynamic-display-name-manager' )
					);
				}
			);
		}

		$settings    = get_option( $this->option_name, array() );
		$user_count  = count_users();
		$total_users = isset( $user_count['total_users'] ) ? $user_count['total_users'] : 0;
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Dynamic Display Name Manager', 'dynamic-display-name-manager' ); ?></h1>

			<form method="post" action="">
				<?php wp_nonce_field( 'ddnm_save_settings', 'ddnm_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Display Name Fields', 'dynamic-display-name-manager' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text">
									<span><?php echo esc_html__( 'Display Name Fields', 'dynamic-display-name-manager' ); ?></span>
								</legend>
								<?php
								$fields = array(
									'username'   => __( 'Username', 'dynamic-display-name-manager' ),
									'email'      => __( 'Email', 'dynamic-display-name-manager' ),
									'first_name' => __( 'First Name', 'dynamic-display-name-manager' ),
									'last_name'  => __( 'Last Name', 'dynamic-display-name-manager' ),
									'website'    => __( 'Website', 'dynamic-display-name-manager' ),
									'role'       => __( 'Role', 'dynamic-display-name-manager' ),
								);

								foreach ( $fields as $field_key => $field_label ) {
									printf(
										'<label><input type="checkbox" name="ddnm_fields[]" value="%s" %s> %s</label><br>',
										esc_attr( $field_key ),
										checked( in_array( $field_key, $settings, true ), true, false ),
										esc_html( $field_label )
									);
								}
								?>
							</fieldset>
							<p class="description">
								<?php echo esc_html__( 'Select which fields to include in the display name (separated by spaces).', 'dynamic-display-name-manager' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'dynamic-display-name-manager' ) ); ?>
			</form>

			<?php if ( ! empty( $settings ) ) : ?>
				<hr>
				<h2><?php echo esc_html__( 'Update Existing Users', 'dynamic-display-name-manager' ); ?></h2>
				<p>
					<?php
					/* translators: %d: Number of users */
					echo esc_html( sprintf( __( 'Apply the current display name format to all existing users (%d users).', 'dynamic-display-name-manager' ), $total_users ) );
					?>
				</p>
				<button type="button" id="start-batch-process" class="button button-primary">
					<?php echo esc_html__( 'Start Batch Update', 'dynamic-display-name-manager' ); ?>
				</button>

				<div id="batch-progress" style="display: none;">
					<p>
						<?php echo esc_html__( 'Processing users...', 'dynamic-display-name-manager' ); ?>
						<span id="progress-text">0 / <?php echo esc_html( $total_users ); ?></span>
					</p>
					<div class="progress-bar">
						<div class="progress-fill" style="width: 0%;"></div>
					</div>
				</div>

				<div id="batch-complete" style="display: none;">
					<p><strong><?php echo esc_html__( 'Batch update completed successfully!', 'dynamic-display-name-manager' ); ?></strong></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function save_settings() {
		// Defense in depth: ensure nonce is valid even though admin_page() already checked.
		if ( ! isset( $_POST['ddnm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ddnm_nonce'] ) ), 'ddnm_save_settings' ) ) {
			return;
		}

		$fields = array();

		if ( isset( $_POST['ddnm_fields'] ) && is_array( $_POST['ddnm_fields'] ) ) {
			$fields = array_map( 'sanitize_text_field', wp_unslash( $_POST['ddnm_fields'] ) );
		}

		// Validate field values
		$allowed_fields = array( 'username', 'email', 'first_name', 'last_name', 'website', 'role' );
		$fields         = array_values( array_intersect( $fields, $allowed_fields ) );

		update_option( $this->option_name, $fields );

		add_action(
			'admin_notices',
			function() {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					esc_html__( 'Settings saved successfully!', 'dynamic-display-name-manager' )
				);
			}
		);
	}

	public function ajax_process_batch() {
		// Verify nonce first
		if ( ! check_ajax_referer( 'ddnm_nonce', 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Security check failed - Invalid nonce', 'dynamic-display-name-manager' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed - Insufficient permissions', 'dynamic-display-name-manager' ) );
		}

		// Only access POST data after nonce verification
		$offset   = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;
		$settings = get_option( $this->option_name, array() );

		if ( empty( $settings ) ) {
			wp_send_json_error( esc_html__( 'No fields selected', 'dynamic-display-name-manager' ) );
		}

		try {
			$users = get_users(
				array(
					'number' => $this->batch_size,
					'offset' => $offset,
					'fields' => 'all',
				)
			);

			$processed = 0;
			foreach ( $users as $user ) {
				if ( $this->update_user_display_name_by_settings( $user->ID, $settings, true ) ) {
					$processed++;
				}
			}

			wp_send_json_success(
				array(
					'processed' => $processed,
					'has_more'  => count( $users ) === $this->batch_size,
				)
			);

		} catch ( Exception $e ) {
			wp_send_json_error(
				sprintf(
					/* translators: %s: Error message */
					esc_html__( 'Processing error: %s', 'dynamic-display-name-manager' ),
					esc_html( $e->getMessage() )
				)
			);
		}
	}

	public function set_new_user_display_name( $user_id ) {
		$settings = get_option( $this->option_name, array() );
		if ( ! empty( $settings ) ) {
			$this->update_user_display_name_by_settings( $user_id, $settings, true );
		}
	}

	public function update_user_display_name( $user_id ) {
		// Prevent infinite loops
		if ( $this->processing_user === $user_id ) {
			return;
		}

		$settings = get_option( $this->option_name, array() );
		if ( ! empty( $settings ) ) {
			$this->update_user_display_name_by_settings( $user_id, $settings );
		}
	}

	private function update_user_display_name_by_settings( $user_id, $settings, $force_update = false ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$display_parts = array();

		foreach ( $settings as $field ) {
			$value = '';

			switch ( $field ) {
				case 'username':
					$value = $user->user_login;
					break;
				case 'email':
					$value = $user->user_email;
					break;
				case 'first_name':
					$value = get_user_meta( $user_id, 'first_name', true );
					break;
				case 'last_name':
					$value = get_user_meta( $user_id, 'last_name', true );
					break;
				case 'website':
					$value = $user->user_url;
					break;
				case 'role':
					$roles = $user->roles;
					$value = ! empty( $roles ) ? ucfirst( $roles[0] ) : '';
					break;
			}

			if ( ! empty( $value ) ) {
				$display_parts[] = sanitize_text_field( $value );
			}
		}

		$new_display_name = implode( ' ', $display_parts );

		// Only update if the display name is different or we're forcing an update
		if ( ! empty( $new_display_name ) && ( $force_update || $user->display_name !== $new_display_name ) ) {
			// Prevent infinite loop by setting the processing flag
			$this->processing_user = $user_id;

			// Temporarily remove the profile_update hook to prevent infinite loop
			remove_action( 'profile_update', array( $this, 'update_user_display_name' ) );

			$result = wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $new_display_name,
				)
			);

			// Re-add the profile_update hook
			add_action( 'profile_update', array( $this, 'update_user_display_name' ) );

			// Reset the processing flag
			$this->processing_user = false;

			return ! is_wp_error( $result );
		}

		return false;
	}
}

// Initialize the plugin
new DynamicDisplayNameManager();
