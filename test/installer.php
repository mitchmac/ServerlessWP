<?php

// Do not use this on a live site! For testing purposes only!

define('WP_INSTALLING', true);

/** Load WordPress Bootstrap */
require_once dirname(__DIR__) . '/wp/wp-load.php';

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/** Load WordPress Translation Install API */
require_once ABSPATH . 'wp-admin/includes/translation-install.php';

/** Load wpdb */
require_once ABSPATH . WPINC . '/class-wpdb.php';

if (!is_blog_installed()) {

    $weblog_title = 'ServerlessWP Site';
    $user_name = 'admin';
    $admin_email = 'admin@example.com';
    $public = TRUE;

    $result = wp_install($weblog_title, $user_name, $admin_email, $public);
    print_r($result);

	update_user_meta( 1, 'default_password_nag', false );
} else {
    echo 'WordPress is already installed.';
}