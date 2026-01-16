<?php

/**
 * Boot Query Monitor from the SQLite Database Integration plugin.
 *
 * When the Query Monitor plugin exists in its standard location, let's check
 * if it is active, so we can boot it eagerly. This is a workaround to avoid
 * SQLite and Query Monitor competing for the "wp-content/db.php" file.
 *
 * This file is a modified version of the original Query Monitor "db.php" file.
 *
 * See: https://github.com/johnbillion/query-monitor/blob/develop/wp-content/db.php
 */

/*
 * In Playground, the SQLite plugin is preloaded without using the "db.php" file.
 * To prevent Query Monitor from injecting its own "db.php" file, we need to set
 * the "QM_DB_SYMLINK" constant to "false".
 */
if ( ! defined( 'QM_DB_SYMLINK' ) ) {
	define( 'QM_DB_SYMLINK', false );
}

// Check if we should load Query Monitor (as per the original "db.php" file).
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'DB_USER' ) ) {
	return;
}

if ( defined( 'QM_DISABLED' ) && QM_DISABLED ) {
	return;
}

if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
	return;
}

if ( 'cli' === php_sapi_name() && ! defined( 'QM_TESTS' ) ) {
	return;
}

if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
	return;
}

if ( is_admin() ) {
	if ( isset( $_GET['action'] ) && 'upgrade-plugin' === $_GET['action'] ) {
		return;
	}

	if ( isset( $_POST['action'] ) && 'update-plugin' === $_POST['action'] ) {
		return;
	}
}

/*
 * Register SQLite enhancements for Query Monitor when plugins are loaded.
 *
 * This will also ensure that the plugin Query Monitor is fully initialized even
 * when we can't load it eagerly, e.g. on a multisite install.
 */
function register_sqlite_enhancements_for_query_monitor() {
	if ( ! class_exists( 'QM_Backtrace' ) ) {
		return;
	}

	require_once __DIR__ . '/plugin.php';

	if ( ! defined( 'SQLITE_QUERY_MONITOR_LOADED' ) ) {
		define( 'SQLITE_QUERY_MONITOR_LOADED', true );
	}
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', 'register_sqlite_enhancements_for_query_monitor' );
}

/*
 * On a multisite install, we can't easily determine the current site eagerly.
 * Therefore, let's bail out and let Query Monitor activate later as a plugin.
 */
if ( is_multisite() ) {
	return;
}

/*
 * Now, let's try to load Query Monitor eagerly, to start logging queries early.
 * When the plugin is installed, we will check the database if it is also active.
 */
global $wpdb;
if ( ! isset( $wpdb ) ) {
	return;
}

// Check if Query Monitor is installed.
if ( defined( 'WP_PLUGIN_DIR' ) ) {
	$plugins_dir = WP_PLUGIN_DIR;
} else {
	$plugins_dir = WP_CONTENT_DIR . '/plugins';
}

$qm_dir = "{$plugins_dir}/query-monitor";
$qm_php = "{$qm_dir}/classes/PHP.php";

if ( ! is_readable( $qm_php ) ) {
	return;
}

// Check if Query Monitor is active.
if ( null === $wpdb->options ) {
	global $table_prefix;
	$wpdb->set_prefix( $table_prefix ?? '' );
}

$query_monitor_active = false;
try {
	// Make sure no errors are displayed when the query fails.
	$show_errors = $wpdb->hide_errors();
	$value       = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1",
			'active_plugins'
		)
	);
	$wpdb->show_errors( $show_errors );

	if ( null !== $value ) {
		$query_monitor_active = in_array(
			'query-monitor/query-monitor.php',
			unserialize( $value->option_value ),
			true
		);
	}
} catch ( Throwable $e ) {
	return;
}

if ( ! $query_monitor_active ) {
	return;
}

// Load Query Monitor eagerly (as per the original "db.php" file).
require_once $qm_php;

if ( ! QM_PHP::version_met() ) {
	return;
}

if ( ! file_exists( "{$qm_dir}/vendor/autoload.php" ) ) {
	add_action( 'all_admin_notices', 'QM_PHP::vendor_nope' );
	return;
}

require_once "{$qm_dir}/vendor/autoload.php";

if ( ! class_exists( 'QM_Backtrace' ) ) {
	return;
}

if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true );
}

// Mark the Query Monitor integration as loaded.
define( 'SQLITE_QUERY_MONITOR_LOADED', true );
