<?php

/**
 * Require SQLite management permission to deactivate an active integration.
 *
 * WordPress checks this capability when one plugin is deactivated, including
 * each plugin in a site-level batch. It skips this per-plugin check for a
 * Network Admin batch, so that case is handled separately below.
 *
 * @access private
 *
 * @param string[] $caps    Primitive capabilities required of the user.
 * @param string   $cap     Capability being checked.
 * @param int      $user_id User ID.
 * @param mixed[]  $args    Additional capability arguments.
 * @return string[] Required primitive capabilities.
 */
function sqlite_plugin_map_deactivation_capability( $caps, $cap, $user_id, $args ) {
	if (
		'deactivate_plugin' === $cap
		&& isset( $args[0] )
		&& plugin_basename( SQLITE_MAIN_FILE ) === $args[0]
		&& sqlite_plugin_has_active_dropin()
	) {
		$caps[] = sqlite_plugin_get_manage_capability();
	}
	return $caps;
}
add_filter( 'map_meta_cap', 'sqlite_plugin_map_deactivation_capability', 10, 4 );

/**
 * WordPress does not check each plugin's permission when deactivating a Network
 * Admin batch. Remove SQLite from the batch when the user cannot manage the
 * network-wide drop-in, while allowing the other selected plugins to continue.
 *
 * @access private
 */
function sqlite_plugin_filter_network_bulk_deactivation() {
	$is_network_bulk_deactivation = is_network_admin()
		&& isset( $_REQUEST['action'] )
		&& is_string( $_REQUEST['action'] )
		&& 'deactivate-selected' === wp_unslash( $_REQUEST['action'] );

	if (
		$is_network_bulk_deactivation
		&& sqlite_plugin_has_active_dropin()
		&& ! current_user_can( sqlite_plugin_get_manage_capability() )
	) {
		/*
		 * Remove only SQLite from the submitted list. WordPress will still check
		 * whether the user may run bulk deactivation and whether the request is
		 * valid before it deactivates the plugins left in the list.
		 */
		$plugins          = isset( $_POST['checked'] ) ? (array) $_POST['checked'] : array();
		$_POST['checked'] = array_values( array_diff( $plugins, array( plugin_basename( SQLITE_MAIN_FILE ) ) ) );
	}
}
add_action( 'load-plugins.php', 'sqlite_plugin_filter_network_bulk_deactivation' );

/**
 * Check whether the SQLite database drop-in is active.
 *
 * @access private
 *
 * @return bool Whether the SQLite database drop-in is active.
 */
function sqlite_plugin_has_active_dropin() {
	return defined( 'SQLITE_DB_DROPIN_VERSION' ) && file_exists( WP_CONTENT_DIR . '/db.php' );
}

/**
 * Get the capability required to manage the SQLite integration.
 *
 * @access private
 *
 * @return string Required capability.
 */
function sqlite_plugin_get_manage_capability() {
	return is_multisite() ? 'manage_network_options' : 'manage_options';
}
