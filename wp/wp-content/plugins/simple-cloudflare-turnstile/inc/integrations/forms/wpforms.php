<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if(get_option('cfturnstile_wpforms')) {

	// Get turnstile field: WP Forms
  if(!empty(get_option('cfturnstile_wpforms_pos')) && get_option('cfturnstile_wpforms_pos') == "after") {
    add_action('wpforms_display_submit_after','cfturnstile_field_wpf_form', 10, 1);
  } else {
    add_action('wpforms_display_submit_before','cfturnstile_field_wpf_form', 10, 1);
  }
	function cfturnstile_field_wpf_form($form_data) {
    if(!cfturnstile_form_disable($form_data['id'], 'cfturnstile_wpforms_disable')) {
      $uniqueId = wp_rand();
      if(!empty(get_option('cfturnstile_wpforms_pos')) && get_option('cfturnstile_wpforms_pos') == "after") { echo "<br/><br/>"; }
      cfturnstile_field_show('.wpforms-submit', 'turnstileWPFCallback', 'wpforms-' . $form_data['id'], '-wpf-' . $uniqueId);
    }
  }

	// WP Forms Check
	add_action('wpforms_process_before', 'cfturnstile_wpf_check', 10, 2);
	function cfturnstile_wpf_check($entry, $form_data) {
    if(!cfturnstile_whitelisted() && !cfturnstile_form_disable($form_data['id'], 'cfturnstile_wpforms_disable')) {
      if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
        $check = cfturnstile_check();
        $success = $check['success'];
        if($success != true) {
          wpforms()->process->errors[ $form_data[ 'id' ] ][ 'header' ] = cfturnstile_failed_message();
        }
      } else {
        wpforms()->process->errors[ $form_data[ 'id' ] ][ 'header' ] = cfturnstile_failed_message();
      }
    } else {
      return;
    }
	}

}
