<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
* Get the field
*/
function cfturnstile_field_wpuf() {
    cfturnstile_field_show('.wpuf-form input[type="submit"]', 'turnstileWPUFCallback', 'wp-user-frontend', '-' . wp_rand());
}

/* 
* Login
*/
if(get_option('cfturnstile_login')) {
    add_action('wpuf_login_form_bottom','cfturnstile_field_wpuf');
}

/*
* Register
*/
if(get_option('cfturnstile_wpuf_register')) {
    add_action('wpuf_reg_form_bottom','cfturnstile_field_wpuf');
    add_action( 'wpuf_process_registration_errors', 'cfturnstile_wpuf_check_register', 10, 1 );
}
// Function to check register
function cfturnstile_wpuf_check_register( $validation_error ) {
    if(!cfturnstile_whitelisted()) {
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
            $check = cfturnstile_check();
            $success = $check['success'];
            if($success != true) {
                $validation_error->add( 'cfturnstile_error', cfturnstile_failed_message() );
            } else {
                $nonce = wp_create_nonce( 'cfturnstile_login_check' );
            }
        } else {
            $validation_error->add( 'cfturnstile_error', cfturnstile_failed_message() );
        }
    }
    return $validation_error;
}
  
/*
* Password Reset
*/
if(get_option('cfturnstile_reset')) {
    remove_action('lostpassword_post','cfturnstile_wp_reset_check', 10, 1);
    add_action('lostpassword_post','cfturnstile_wpuf_check_reset', 20);
}
// Function to check forms
function cfturnstile_wpuf_check_reset() {
    if(!cfturnstile_whitelisted()) {
        $check = cfturnstile_check();
        $success = $check['success'];
        if($success != true) {
            wp_die( '<p><strong>' . esc_html__( 'ERROR:', 'simple-cloudflare-turnstile' ) . '</strong> ' . cfturnstile_failed_message() . '</p>', 'simple-cloudflare-turnstile', array( 'response'  => 403, 'back_link' => 1, ) );
        } else {
            $nonce = wp_create_nonce( 'cfturnstile_login_check' );
        }
    }
}

/*
* Forms
*/
if(get_option('cfturnstile_wpuf_forms')) {
    add_action('wpuf_add_post_form_bottom','cfturnstile_field_wpuf_form');
    add_action( 'wpuf_add_post_validate', 'cfturnstile_wpuf_check', 20 );
}
// Function to check forms
function cfturnstile_wpuf_check() {
    if(!cfturnstile_whitelisted()) {
        $check = cfturnstile_check();
        $success = $check['success'];
        if($success != true) {
            $errors = cfturnstile_failed_message();
        }
    }
    if(empty($errors)) { return; }
    return $errors;
}
// Function to add field
function cfturnstile_field_wpuf_form() {
    ?>
    <li class="wpuf-el post_content">
        <div class="wpuf-label"></div>
        <div class="wpuf-fields">
            <?php cfturnstile_field_show('.wpuf-form input[type="submit"]', 'turnstileWPUFCallback', 'wp-user-frontend', '-' . wp_rand()); ?>
        </div>
    </li>
    <?php
}
