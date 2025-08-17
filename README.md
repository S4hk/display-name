# Dynamic Display Name Manager

A WordPress plugin that allows administrators to configure user display names using selected user fields with efficient batch processing.

## Features

- **Flexible Display Name Configuration**: Choose from username, email, first name, last name, website, and role fields
- **Batch Processing**: Update existing users efficiently with progress tracking
- **Automatic New User Handling**: Newly registered users automatically get the configured display name format
- **Real-time Updates**: Profile updates trigger display name refresh
- **User-friendly Interface**: Clean admin interface with progress indicators

## Installation

1. Download or clone this plugin to your WordPress plugins directory:
   ```
   wp-content/plugins/dynamic-display-name-manager/
   ```

2. Activate the plugin through the WordPress admin panel:
   - Go to **Plugins** > **Installed Plugins**
   - Find "Dynamic Display Name Manager"
   - Click **Activate**

## Usage

### Configuration

1. Navigate to **Tools** > **Display Name Manager** in your WordPress admin
2. Select the fields you want to include in user display names:
   - ☐ Username
   - ☐ Email
   - ☐ First Name
   - ☐ Last Name
   - ☐ Website
   - ☐ Role
3. Click **Save Settings**

### Alternative Access

You can also access the plugin settings directly from the Plugins page:
1. Go to **Plugins** > **Installed Plugins**
2. Find "Dynamic Display Name Manager"
3. Click the **Settings** link below the plugin name

### Updating Existing Users

After configuring your desired fields:

1. Scroll down to the "Update Existing Users" section
2. Click **Start Batch Update**
3. Monitor the progress bar as users are processed
4. Wait for the "Batch update completed successfully!" message

### Automatic Handling

- **New Registrations**: New users will automatically receive display names based on your configuration
- **Profile Updates**: When users update their profiles, display names are refreshed automatically

## Technical Details

### Batch Processing

- Processes users in batches of 50 to prevent timeouts
- Uses AJAX for non-blocking updates
- Provides real-time progress feedback
- Handles large user bases efficiently

### Display Name Format

Display names are created by concatenating selected fields with spaces:
- Example: If username, first name, and last name are selected
- Result: "johnsmith John Smith"

### Hooks and Filters

The plugin uses these WordPress hooks:
- `user_register`: Sets display name for new users
- `profile_update`: Updates display name when profiles change
- `wp_ajax_ddnm_process_batch`: Handles batch processing requests

## Code Structure

```
dynamic-display-name-manager/
├── dynamic-display-name-manager.php  # Main plugin file
├── admin.js                         # Admin interface JavaScript
├── admin.css                        # Admin interface styling
└── README.md                        # This documentation
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Administrator privileges for configuration

## Security Features

- Nonce verification for all AJAX requests
- Capability checks for admin functions
- Input sanitization for all user data
- SQL injection protection through WordPress APIs

## Troubleshooting

### Batch Processing Stops

If batch processing stops unexpectedly:
1. Check your server's PHP max execution time
2. Verify JavaScript console for errors
3. Try processing smaller batches by reducing users

### Display Names Not Updating

1. Ensure you've selected at least one field
2. Check that users have data in the selected fields
3. Verify the plugin is active and configured

### Performance Considerations

- Large user bases (10,000+ users) may take several minutes to process
- The plugin processes 50 users per batch to prevent timeouts
- Consider running batch updates during low-traffic periods

## Support

For issues or feature requests:
1. Check the WordPress error logs
2. Verify plugin compatibility with your WordPress version
3. Test with default WordPress themes and minimal plugins

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### Version 1.0.0
- Initial release
- Basic field selection functionality
- Batch processing with progress tracking
- Automatic new user handling
- Profile update integration
