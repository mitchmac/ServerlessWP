<?php
if(!defined('ABSPATH')) {
    exit;
}

if(get_option('cfturnstile_login')) {
    add_action('wpum_before_submit_button_login_form', 'cfturnstile_field_login');
}

// Password Reset Form
if(get_option('cfturnstile_reset')) {
    add_action('wpum_before_submit_button_password_recovery_form', 'cfturnstile_field_reset');
    add_filter('submit_wpum_form_validate_fields', 'cfturnstile_wpum_password_recovery_validation', 10, 4);
    function cfturnstile_wpum_password_recovery_validation($pass, $values, $form, $form_data) {
        // Check if this is a password recovery form
        if($form_data !== 'password-recovery') {
            return $pass;
        }
        if (function_exists('cfturnstile_check')) {
            $check = cfturnstile_check();
            $success = $check['success'];
            if($success != true) {
                return new WP_Error('cfturnstile_error', 'Please verify that you are human.');
            }
        }
        return $pass;
    }
}

// Registration Form
if(get_option('cfturnstile_register')) {
    add_action('wpum_before_submit_button_registration_form', 'cfturnstile_field_register');
    add_action('wpum_before_registration_start', 'cfturnstile_wpum_before_registration_start');
    function cfturnstile_wpum_before_registration_start() {
        if (function_exists('cfturnstile_check')) {
            $check = cfturnstile_check();
            $success = $check['success'];
            if($success != true) {
                throw new Exception( 'Please verify that you are human.' );
            }
        }
    }
}