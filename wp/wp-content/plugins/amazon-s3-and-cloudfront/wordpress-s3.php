<?php
/*
Plugin Name: WP Offload Media Lite
Plugin URI: http://wordpress.org/extend/plugins/amazon-s3-and-cloudfront/
Description: Automatically copies media uploads to Amazon S3, DigitalOcean Spaces or Google Cloud Storage for storage and delivery. Optionally configure Amazon CloudFront or another CDN for even faster delivery.
Author: Delicious Brains
Version: 3.2.5
Author URI: https://deliciousbrains.com/?utm_campaign=WP%2BOffload%2BS3&utm_source=wordpress.org&utm_medium=free%2Bplugin%2Blisting
Network: True
Text Domain: amazon-s3-and-cloudfront
Domain Path: /languages/

// Copyright (c) 2013 Delicious Brains. All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
// **********************************************************************
//
// Forked Amazon S3 for WordPress with CloudFront (http://wordpress.org/extend/plugins/tantan-s3-cloudfront/)
// which is a fork of Amazon S3 for WordPress (http://wordpress.org/extend/plugins/tantan-s3/).
// Then completely rewritten.
*/

// phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable

$GLOBALS['aws_meta']['amazon-s3-and-cloudfront']['version'] = '3.2.5';

require_once dirname( __FILE__ ) . '/classes/as3cf-compatibility-check.php';

add_action( 'activated_plugin', array( 'AS3CF_Compatibility_Check', 'deactivate_other_instances' ) );

global $as3cf_compat_check;
$as3cf_compat_check = new AS3CF_Compatibility_Check(
	'WP Offload Media Lite',
	'amazon-s3-and-cloudfront',
	__FILE__
);

/**
 * @throws Exception
 */
function as3cf_init() {
	if ( class_exists( 'Amazon_S3_And_CloudFront' ) ) {
		return;
	}

	global $as3cf_compat_check;

	if ( method_exists( 'AS3CF_Compatibility_Check', 'is_plugin_active' ) && $as3cf_compat_check->is_plugin_active( 'amazon-s3-and-cloudfront-pro/amazon-s3-and-cloudfront-pro.php' ) ) {
		// Don't load if pro plugin installed
		return;
	}

	if ( ! $as3cf_compat_check->is_compatible() ) {
		return;
	}

	global $as3cf;
	$abspath = dirname( __FILE__ );

	// Autoloader.
	require_once $abspath . '/wp-offload-media-autoloader.php';
	new WP_Offload_Media_Autoloader( 'WP_Offload_Media', $abspath );

	require_once $abspath . '/include/functions.php';
	require_once $abspath . '/classes/as3cf-utils.php';
	require_once $abspath . '/classes/as3cf-error.php';
	require_once $abspath . '/classes/as3cf-filter.php';
	require_once $abspath . '/classes/filters/as3cf-local-to-s3.php';
	require_once $abspath . '/classes/filters/as3cf-s3-to-local.php';
	require_once $abspath . '/classes/as3cf-notices.php';
	require_once $abspath . '/classes/as3cf-plugin-base.php';
	require_once $abspath . '/classes/as3cf-plugin-compatibility.php';
	require_once $abspath . '/classes/amazon-s3-and-cloudfront.php';

	// Load settings and core components.
	$as3cf = new Amazon_S3_And_CloudFront( __FILE__ );

	// Initialize managers and their registered components.
	do_action( 'as3cf_init', $as3cf );

	// Set up initialized components, e.g. add integration hooks.
	do_action( 'as3cf_setup', $as3cf );

	// Plugin is ready to rock, let 3rd parties know.
	do_action( 'as3cf_ready', $as3cf );
}

add_action( 'init', 'as3cf_init' );

// If AWS still active need to be around to satisfy addon version checks until upgraded.
add_action( 'aws_init', 'as3cf_init', 11 );
