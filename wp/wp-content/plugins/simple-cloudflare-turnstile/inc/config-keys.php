<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Allow defining keys in wp-config.php and override the saved options everywhere.
 *
 * Define the following in wp-config.php to use them:
 *  define('CF_TURNSTILE_SITE_KEY', 'your-site-key');
 *  define('CF_TURNSTILE_SECRET_KEY', 'your-secret-key');
 */
add_filter('option_cfturnstile_key', function ($value) {
	if (defined('CF_TURNSTILE_SITE_KEY') && CF_TURNSTILE_SITE_KEY) {
		return CF_TURNSTILE_SITE_KEY;
	}
	return $value;
});
add_filter('option_cfturnstile_secret', function ($value) {
	if (defined('CF_TURNSTILE_SECRET_KEY') && CF_TURNSTILE_SECRET_KEY) {
		return CF_TURNSTILE_SECRET_KEY;
	}
	return $value;
});
add_filter('pre_option_cfturnstile_key', function ($default) {
	if (defined('CF_TURNSTILE_SITE_KEY') && CF_TURNSTILE_SITE_KEY) {
		return CF_TURNSTILE_SITE_KEY;
	}
	return $default;
});
add_filter('pre_option_cfturnstile_secret', function ($default) {
	if (defined('CF_TURNSTILE_SECRET_KEY') && CF_TURNSTILE_SECRET_KEY) {
		return CF_TURNSTILE_SECRET_KEY;
	}
	return $default;
});
// Prevent updating stored options when constants are defined (keeps existing DB values intact)
add_filter('pre_update_option_cfturnstile_key', function ($value, $old_value) {
	if (defined('CF_TURNSTILE_SITE_KEY') && CF_TURNSTILE_SITE_KEY) {
		return $old_value;
	}
	return $value;
}, 10, 2);
add_filter('pre_update_option_cfturnstile_secret', function ($value, $old_value) {
	if (defined('CF_TURNSTILE_SECRET_KEY') && CF_TURNSTILE_SECRET_KEY) {
		return $old_value;
	}
	return $value;
}, 10, 2);