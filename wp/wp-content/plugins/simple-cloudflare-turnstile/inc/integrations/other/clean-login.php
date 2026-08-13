<?php
if(!defined('ABSPATH')) {
    exit;
}

add_action('cleanlogin_after_login_form', 'cfturnstile_field_login');
add_action('cleanlogin_after_register_form', 'cfturnstile_field_register');
add_action('cleanlogin_after_resetpassword_form', 'cfturnstile_field_reset');