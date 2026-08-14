<?php
/**
 * Handle the SQLite deactivation.
 */

/**
 * Delete the db.php file in wp-content.
 *
 * When the plugin gets merged in wp-core, this is not to be ported.
 *
 * @param bool $network_deactivating Whether the plugin is being deactivated network-wide.
 */
function sqlite_plugin_remove_db_file( $network_deactivating = false ) {
	if ( ! sqlite_plugin_has_active_dropin() ) {
		return;
	}

	// WP-CLI and other programmatic callers may deactivate without a current user.
	// In those cases, the caller is responsible for authorization.
	if ( is_user_logged_in() && ! current_user_can( sqlite_plugin_get_manage_capability() ) ) {
		return;
	}

	global $wp_filesystem;

	require_once ABSPATH . '/wp-admin/includes/file.php';

	// Init the filesystem if needed, then delete custom drop-in.
	if ( $wp_filesystem || WP_Filesystem() ) {
		// Flush any persistent cache.
		wp_cache_flush();
		// Delete the drop-in.
		$wp_filesystem->delete( WP_CONTENT_DIR . '/db.php' );
		// Flush the cache again to mitigate a possible race condition.
		wp_cache_flush();
	}

	// Run an action on `shutdown`, to deactivate the option in the MySQL database.
	add_action(
		'shutdown',
		function () use ( $network_deactivating ) {
			global $table_prefix;

			// Get credentials for the MySQL database.
			$dbuser     = defined( 'DB_USER' ) ? DB_USER : '';
			$dbpassword = defined( 'DB_PASSWORD' ) ? DB_PASSWORD : '';
			$dbname     = defined( 'DB_NAME' ) ? DB_NAME : '';
			$dbhost     = defined( 'DB_HOST' ) ? DB_HOST : '';

			// Init a connection to the MySQL database.
			$wpdb_mysql = new wpdb( $dbuser, $dbpassword, $dbname, $dbhost );
			$wpdb_mysql->set_prefix( $table_prefix );

			sqlite_plugin_deactivate_in_mysql( $wpdb_mysql, $network_deactivating );
		},
		PHP_INT_MAX
	);
	// Flush any persistent cache.
	wp_cache_flush();
}
register_deactivation_hook( SQLITE_MAIN_FILE, 'sqlite_plugin_remove_db_file' ); // Remove db.php file on plugin deactivation.

/**
 * Deactivate the plugin in the original MySQL database.
 *
 * @access private
 *
 * @param wpdb $wpdb_mysql          MySQL database connection.
 * @param bool $network_deactivating Whether the plugin is being deactivated network-wide.
 */
function sqlite_plugin_deactivate_in_mysql( $wpdb_mysql, $network_deactivating ) {
	if ( $network_deactivating ) {
		$network_id   = get_current_network_id();
		$row          = $wpdb_mysql->get_row( $wpdb_mysql->prepare( "SELECT meta_value AS active_plugins FROM $wpdb_mysql->sitemeta WHERE site_id = %d AND meta_key = %s LIMIT 1", $network_id, 'active_sitewide_plugins' ) );
		$table        = $wpdb_mysql->sitemeta;
		$value_column = 'meta_value';
		$where        = array(
			'site_id'  => $network_id,
			'meta_key' => 'active_sitewide_plugins',
		);
	} else {
		$row          = $wpdb_mysql->get_row( $wpdb_mysql->prepare( "SELECT option_value AS active_plugins FROM $wpdb_mysql->options WHERE option_name = %s LIMIT 1", 'active_plugins' ) );
		$table        = $wpdb_mysql->options;
		$value_column = 'option_value';
		$where        = array( 'option_name' => 'active_plugins' );
	}

	if ( ! is_object( $row ) ) {
		return;
	}

	$active_plugins = maybe_unserialize( $row->active_plugins );
	if ( ! is_array( $active_plugins ) ) {
		return;
	}

	$item = plugin_basename( SQLITE_MAIN_FILE );
	if ( $network_deactivating ) {
		$key = $item;
	} else {
		$key = array_search( $item, $active_plugins, true );
	}

	if ( false === $key || ! array_key_exists( $key, $active_plugins ) ) {
		return;
	}

	unset( $active_plugins[ $key ] );
	$wpdb_mysql->update( $table, array( $value_column => maybe_serialize( $active_plugins ) ), $where );
}
