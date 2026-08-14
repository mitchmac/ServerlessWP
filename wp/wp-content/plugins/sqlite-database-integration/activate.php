<?php
/**
 * Handle the SQLite activation.
 */

/**
 * Redirect to the plugin's admin screen on activation.
 *
 * @param string $plugin The plugin basename.
 */
function sqlite_plugin_activation_redirect( $plugin ) {
	if (
		plugin_basename( SQLITE_MAIN_FILE ) !== $plugin
		|| ! current_user_can( sqlite_plugin_get_manage_capability() )
	) {
		return;
	}

	if ( wp_safe_redirect( sqlite_plugin_get_admin_page_url() ) ) {
		exit;
	}
}
add_action( 'activated_plugin', 'sqlite_plugin_activation_redirect' );

/**
 * Check the URL to ensure we're on the plugin page,
 * the user has clicked the button to install SQLite,
 * the user has permission to manage the database drop-in, and the nonce is valid.
 * If the above conditions are met, run the sqlite_plugin_copy_db_file() function,
 * and redirect to the install screen.
 */
function sqlite_activation() {
	if (
		! isset( $_GET['page'], $_GET['confirm-install'] )
		|| 'sqlite-integration' !== $_GET['page']
	) {
		return;
	}

	if ( ! current_user_can( sqlite_plugin_get_manage_capability() ) ) {
		wp_die(
			esc_html__( 'Sorry, you are not allowed to install the SQLite database drop-in.', 'sqlite-database-integration' ),
			403
		);
	}

	check_admin_referer( 'sqlite-install' );

	sqlite_plugin_copy_db_file();

	// WordPress will automatically redirect to the install screen here.
	wp_redirect( admin_url() );
	exit;
}
add_action( 'admin_init', 'sqlite_activation' );

// Flush the cache at the last moment before the redirect.
add_filter(
	'x_redirect_by',
	function ( $result ) {
		wp_cache_flush();
		return $result;
	},
	PHP_INT_MAX,
	1
);

/**
 * Add the db.php file in wp-content.
 *
 * When the plugin gets merged in wp-core, this is not to be ported.
 */
function sqlite_plugin_copy_db_file() {
	// Bail early if the pdo_sqlite extension is not loaded.
	if ( ! extension_loaded( 'pdo_sqlite' ) ) {
		return;
	}

	$destination = WP_CONTENT_DIR . '/db.php';

	/*
	 * When an existing "db.php" drop-in is detected, let's check if it's a known
	 * plugin that we can continue supporting even when we override the drop-in.
	 */
	$override_db_dropin = false;
	if ( file_exists( $destination ) ) {
		// Check for the Query Monitor plugin.
		// When "QM_DB" exists, it must have been loaded via the "db.php" file.
		if ( class_exists( 'QM_DB', false ) ) {
			$override_db_dropin = true;
		}

		if ( $override_db_dropin ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				WP_Filesystem();
			}
			$wp_filesystem->delete( $destination );
		}
	}

	// Place database drop-in if not present yet, except in case there is
	// another database drop-in present already.
	if ( ! defined( 'SQLITE_DB_DROPIN_VERSION' ) && ! file_exists( $destination ) ) {
		// Init the filesystem to allow copying the file.
		global $wp_filesystem;

		require_once ABSPATH . '/wp-admin/includes/file.php';

		// Init the filesystem if needed, then copy the file, replacing contents as needed.
		if ( ( $wp_filesystem || WP_Filesystem() ) && $wp_filesystem->touch( $destination ) ) {

			// Get the db.copy.php file contents, replace placeholders and write it to the destination.
			$file_contents = str_replace(
				array(
					'{SQLITE_IMPLEMENTATION_FOLDER_PATH}',
					'{SQLITE_PLUGIN}',
				),
				array(
					__DIR__,
					str_replace( WP_PLUGIN_DIR . '/', '', SQLITE_MAIN_FILE ),
				),
				file_get_contents( __DIR__ . '/db.copy' )
			);

			$wp_filesystem->put_contents( $destination, $file_contents );
		}
	}
}
