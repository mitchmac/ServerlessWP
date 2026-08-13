<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get turnstile field: Ultimate Member
if(get_option('cfturnstile_um_login')) { add_action('um_after_login_fields','cfturnstile_field_um_login'); }
if(get_option('cfturnstile_um_register')) { add_action('um_after_register_fields','cfturnstile_field_um_register'); }
if(get_option('cfturnstile_um_password')) { add_action('um_after_password_reset_fields','cfturnstile_field_um_password'); }
function cfturnstile_field_um_login() { cfturnstile_field_show('#um-submit-btn', 'turnstileUMCallback', 'ultimate-member', '-um-login'); }
function cfturnstile_field_um_register() { cfturnstile_field_show('#um-submit-btn', 'turnstileUMCallback', 'ultimate-member', '-um-register'); }
function cfturnstile_field_um_password() { cfturnstile_field_show('#um-submit-btn', 'turnstileUMCallback', 'ultimate-member', '-um-password'); }

// Ultimate Member Check
if(get_option('cfturnstile_um_login')) { add_action( 'um_submit_form_errors_hook_login', 'cfturnstile_um_check', 5, 2 ); }
if(get_option('cfturnstile_um_register')) { add_action( 'um_submit_form_errors_hook__registration', 'cfturnstile_um_check', 20, 2 ); }
if(get_option('cfturnstile_um_password')) { add_action( 'um_reset_password_errors_hook', 'cfturnstile_um_check', 20, 2 ); }
function cfturnstile_um_check( $args, $form_data = array() ) {

  $is_login = cfturnstile_um_is_login_context( $form_data );
  $user_id = $is_login ? cfturnstile_um_get_login_user_id( $args ) : 0;
  $verified_key = cfturnstile_um_verified_key( $form_data );

  // Check if already validated (cache-friendly, no PHP session)
  if( cfturnstile_get_verified( $verified_key ) || ( $user_id && cfturnstile_get_verified( 'cfturnstile_login_checked_' . $user_id ) ) ) {
    return;
  }

  // Whitelisted
  if(cfturnstile_whitelisted()) {
    return;
  }

  // Check
  if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    $check = cfturnstile_check();
    $success = $check['success'];
    if($success != true) {
      UM()->form()->add_error( 'cfturnstile', cfturnstile_failed_message() );
      if ( $is_login ) {
        cfturnstile_um_block_login_validation();
      }
    } else {
      cfturnstile_set_verified( $verified_key );

      if ( $is_login ) {
        $GLOBALS['cfturnstile_um_login_verified'] = true;
        if ( $user_id ) {
          cfturnstile_set_verified( 'cfturnstile_login_checked_' . $user_id, '', 300 );
        }
      }
    }
  } else {
    UM()->form()->add_error( 'cfturnstile', cfturnstile_failed_message() );
    if ( $is_login ) {
      cfturnstile_um_block_login_validation();
    }
  }

}

function cfturnstile_um_block_login_validation() {
  remove_action( 'um_submit_form_errors_hook_login', 'um_submit_form_errors_hook_login' );
}

function cfturnstile_um_verified_key( $form_data = array() ) {
  $current_filter = current_filter();

  if ( cfturnstile_um_is_login_context( $form_data ) ) {
    return 'cfturnstile_um_login_checked';
  }

  if ( 'um_submit_form_errors_hook__registration' === $current_filter ) {
    return 'cfturnstile_um_register_checked';
  }

  if ( 'um_reset_password_errors_hook' === $current_filter ) {
    return 'cfturnstile_um_password_checked';
  }

  return 'cfturnstile_um_checked';
}

function cfturnstile_um_is_login_context( $form_data = array() ) {
  if ( 'um_submit_form_errors_hook_login' !== current_filter() ) {
    return false;
  }

  if ( is_array( $form_data ) && isset( $form_data['mode'] ) && 'login' !== $form_data['mode'] ) {
    return false;
  }

  return true;
}

function cfturnstile_um_get_login_user_id( $args ) {
  $args = is_array( $args ) ? $args : array();
  $login = '';
  $login_field = '';
  $fields = array( 'username', 'user_login', 'log', 'user_email', 'email' );

  foreach ( $fields as $field ) {
    if ( isset( $args[ $field ] ) && ! is_array( $args[ $field ] ) ) {
      $login = sanitize_text_field( wp_unslash( $args[ $field ] ) );
      $login_field = $field;
      break;
    }

    if ( isset( $_POST[ $field ] ) && ! is_array( $_POST[ $field ] ) ) {
      $login = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
      $login_field = $field;
      break;
    }
  }

  if ( ! $login ) {
    return 0;
  }

  if ( 'user_email' === $login_field || 'email' === $login_field || ( 'username' === $login_field && is_email( $login ) ) ) {
    $user = get_user_by( 'email', $login );
  } else {
    $user = get_user_by( 'login', $login );
  }

  return $user ? (int) $user->ID : 0;
}

add_filter( 'cfturnstile_wp_login_checks', 'cfturnstile_um_skip_wp_login_check', 10, 1 );
function cfturnstile_um_skip_wp_login_check( $skip ) {
  if ( $skip ) {
    return $skip;
  }

  if ( ! empty( $GLOBALS['cfturnstile_um_login_verified'] ) && get_option( 'cfturnstile_um_login' ) && cfturnstile_get_verified( 'cfturnstile_um_login_checked' ) ) {
    unset( $GLOBALS['cfturnstile_um_login_verified'] );
    cfturnstile_clear_verified( 'cfturnstile_um_login_checked' );
    return true;
  }

  return $skip;
}

// Get Error Message
function cfturnstile_um_error_message() {
  echo '<p style="color: red; font-weight: bold;">' . cfturnstile_failed_message() . '</p>';
}
// Clear verification flag on login
add_action('um_user_login', 'cfturnstile_um_login_clear', 10, 1);
function cfturnstile_um_login_clear($args) { 
	cfturnstile_clear_verified( 'cfturnstile_um_login_checked' );
}
