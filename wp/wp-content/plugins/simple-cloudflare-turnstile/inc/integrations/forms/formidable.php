<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if(get_option('cfturnstile_formidable')) {

	// Get turnstile field: Formidable Forms
	add_action('frm_submit_button_html','cfturnstile_field_formidable_form', 10, 2);
	function cfturnstile_field_formidable_form($button, $args) {

    if(!cfturnstile_form_disable($args['form']->id, 'cfturnstile_formidable_disable')) {

      $unique_id = wp_rand();

    	ob_start();
      cfturnstile_field_show('.frm_forms .frm_button_submit', 'turnstileFormidableCallback', 'formidable-form-' . $args['form']->id, '-fmdble-' . $unique_id);
    	$cfturnstile = ob_get_contents();
    	ob_end_clean();
    	wp_reset_postdata();

      if(!empty(get_option('cfturnstile_formidable_pos')) && get_option('cfturnstile_formidable_pos') == "after") {
  		  return $button . $cfturnstile;
      } else {
        return $cfturnstile . $button;
      }

    } else {

      return $button;

    }

	}

	// Formidable Forms Check
	add_action('frm_validate_entry', 'cfturnstile_formidable_check', 10, 2);
	function cfturnstile_formidable_check($errors, $values){
    if(!cfturnstile_form_disable($values['form_id'], 'cfturnstile_formidable_disable')) {
      $check = cfturnstile_check();
      $success = $check['success'];
      if($success != true) {
  			$errors['cfturnstile_error'] = cfturnstile_failed_message();
  		}
    }
    return $errors;
	}

}
