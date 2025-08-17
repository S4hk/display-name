=== Dynamic Display Name Manager ===
Contributors: s4hk
Donate link: https://github.com/s4hk
Tags: display name, user management, batch processing, user fields
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Configure user display names using selected user fields (username, email, first name, last name, website, role) with batch processing.

== Description ==

Dynamic Display Name Manager allows administrators to configure user display names using selected user fields with efficient batch processing.

= Features =

* **Flexible Display Name Configuration**: Choose from username, email, first name, last name, website, and role fields
* **Batch Processing**: Update existing users efficiently with progress tracking
* **Automatic New User Handling**: Newly registered users automatically get the configured display name format
* **Real-time Updates**: Profile updates trigger display name refresh
* **User-friendly Interface**: Clean admin interface with progress indicators

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/dynamic-display-name-manager/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Tools->Display Name Manager screen to configure your settings

== Frequently Asked Questions ==

= How do I configure display names? =

Navigate to Tools > Display Name Manager in your WordPress admin, select the fields you want to include, and click Save Settings.

= Can I update existing users? =

Yes, after configuring your desired fields, use the "Start Batch Update" button to apply the new format to all existing users.

= What happens to new users? =

New users will automatically receive display names based on your configuration when they register.

== Screenshots ==

1. Main settings page showing field selection options
2. Batch processing interface with progress tracking

== Changelog ==

= 1.0.0 =
* Initial release
* Basic field selection functionality
* Batch processing with progress tracking
* Automatic new user handling
* Profile update integration

== Upgrade Notice ==

= 1.0.0 =
Initial release of Dynamic Display Name Manager.

== License ==

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
