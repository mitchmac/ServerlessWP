# TiDB Compatibility Plugin for WordPress

TiDB Compatibility Plugin for WordPress

[TiDB](https://www.pingcap.com) is a high-performance database that is compatible with the MySQL protocol. Since MySQL has deprecated the `SQL_CALC_FOUND_ROWS` function, TiDB also has no intention of offering the `SQL_CALC_FOUND_ROWS function`. This leads to an error in WordPress when using TiDB, indicating that `SQL_CALC_FOUND_ROWS` is not supported, and submissions cannot be displayed correctly.

WordPress is also currently working on this issue, but it seems that more time is needed.
[#47280 Remove usage of deprecated MySQL SQL_CALC_FOUND_ROWS from WP_Query](https://github.com/WordPress/wordpress-develop/pull/3863)

This plugin solves the issue of TiDB not providing the `SQL_CALC_FOUND_ROWS function`. **Once this plugin is activated, parts of `WP_Query` that use `SQL_CALC_FOUND_ROWS` will be replaced with the `COUNT(*)` function.**

This plugin is entirely based on the method mentioned by [@akramipro](https://github.com/AkramiPro) in the [article](https://core.trac.wordpress.org/ticket/47280), and this solution works perfectly and addresses the issue. I've turned this method into a plugin so that those using TiDB can easily resolve this problem. Many thanks to [@akramipro](https://github.com/AkramiPro) for the excellent work, and I hope the official WordPress team can address this issue sooner.

### Installation

#### From the WordPress plugin directory

This plugin has been accepted into the WordPress plugin directory: https://wordpress.org/plugins/tidb-compatibility/

The easiest way to install it is from the WordPress dashboard or cfom the command-line wit [wp-cli](https://make.wordpress.org/cli/): `wp plugin install tidb-compatibility`

#### Manually from zip file

Alternatively you can also install this plugin manually following these steps:

1. Download the Plugin: First, download the plugin from https://github.com/pingcap/wordpress-tidb-plugin/releases as a `.zip` file.

2. Access Your Website's File Manager: You can do this through your web hosting control panel (like cPanel) or via an FTP client.

3. Upload the Plugin: Navigate to the 'wp-content/plugins' directory. Upload the plugin .zip file here.

4. Extract the Plugin: After uploading, extract the .zip file. This will create a new folder with the plugin files.

5. Activate the Plugin: Go to your WordPress admin area. Click on 'Plugins' in the menu, find your newly uploaded plugin and click 'Activate'.
