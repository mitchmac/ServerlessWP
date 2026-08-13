<?php
if (!defined('ABSPATH')) {
	exit;
}

if (get_option("cfturnstile_mailpoet")) {

    // Add Turnstile to MailPoet
    function cfturnstile_field_mailpoet( $formHtml ) {

        wp_enqueue_script('cfturnstile-mailpoet', plugins_url('simple-cloudflare-turnstile/js/integrations/mailpoet.js'), '', '1.1', true);

        $uniqueId = wp_rand();

        ob_start();
        cfturnstile_field_show('.mailpoet_submit', 'turnstileMailpoetCallback', 'mailpoet-' . $uniqueId, '-mailpoet');
        $turnstile = ob_get_clean();

        $formHtml = preg_replace( '/(<input[^>]*class="mailpoet_submit"[^>]*>)/', $turnstile . '$1', $formHtml );

        return $formHtml;

    }
    add_filter( 'mailpoet_form_widget_post_process', 'cfturnstile_field_mailpoet' );

    // Check Mailpoet Submission
    add_action('mailpoet_subscription_before_subscribe', 'cfturnstile_mailpoet_check', 10, 3);
    function cfturnstile_mailpoet_check($data, $segmentIds, $form) {

        $error_message = cfturnstile_failed_message();

        $posted_data = ( isset($_POST['data']) && is_array($_POST['data']) ) ? $_POST['data'] : array();
        $token = isset($posted_data['cf-turnstile-response']) ? sanitize_text_field($posted_data['cf-turnstile-response']) : '';
        if ( $token === '' && isset($_POST['cf-turnstile-response']) ) {
            $token = sanitize_text_field($_POST['cf-turnstile-response']);
        }

        if (cfturnstile_whitelisted()) {
            return;
        }

        // MailPoet posts fields under $_POST['data'][...]. The failsafe logic in cfturnstile_check()
        // reads from $_POST, so sync those values when failover is enabled.
        if ( get_option('cfturnstile_failover') ) {
            if ( isset($posted_data['cfturnstile_failsafe']) && !is_array($posted_data['cfturnstile_failsafe']) ) {
                $_POST['cfturnstile_failsafe'] = sanitize_text_field($posted_data['cfturnstile_failsafe']);
            }
            if ( isset($posted_data['g-recaptcha-response']) && !is_array($posted_data['g-recaptcha-response']) ) {
                $_POST['g-recaptcha-response'] = sanitize_text_field($posted_data['g-recaptcha-response']);
            }
            if ( $token !== '' ) {
                $_POST['cf-turnstile-response'] = $token;
            }
        }

        $check = cfturnstile_check($token);
        $success = (is_array($check) && isset($check['success'])) ? $check['success'] : false;
        if ($success != true) {
            throw new \MailPoet\UnexpectedValueException($error_message);
        }

    }

}