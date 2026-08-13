<?php
if (!defined('ABSPATH')) {
	exit;
}
if (get_option("cfturnstile_kadence")) {

    /**
     * Inject Turnstile into Kadence blocks on the server.
     * This is more reliable than JS injection because Kadence Advanced Form is a dynamic block.
     */
    add_filter('render_block', 'cfturnstile_kadence_inject_turnstile', 10, 2);
    function cfturnstile_kadence_inject_turnstile($block_content, $block) {
        if (is_admin()) {
            return $block_content;
        }
        if (empty($block_content) || !is_array($block) || empty($block['blockName'])) {
            return $block_content;
        }
        if (cfturnstile_whitelisted()) {
            return $block_content;
        }

        $block_name = (string) $block['blockName'];
        if ($block_name !== 'kadence/advanced-form' && $block_name !== 'kadence/form') {
            return $block_content;
        }

        // Avoid duplicate injection.
        if (strpos($block_content, 'cf-turnstile') !== false) {
            return $block_content;
        }

        $unique_id = wp_rand();
        $button_selector = '.kb-adv-form-submit-button, .kb-submit-field .kb-button, .kb-form-submit .kb-button';

        ob_start();
        cfturnstile_field_show($button_selector, 'turnstileKadenceCallback', 'kdforms-' . $unique_id, '-kadence-' . $unique_id);
        $turnstile_field = ob_get_clean();

        // Kadence layout is tight; remove extra line breaks and the generic failed text block.
        $turnstile_field = preg_replace('/<br.*?>/', '', $turnstile_field);
        $turnstile_field = preg_replace('/<div class="cf-turnstile-failed-text.*?<\/div>/', '', $turnstile_field);

        // Insert immediately before the submit button when possible.
        $pattern = '/(<[^>]*class=("|\")[^\"\"]*(wp-block-kadence-advanced-form-submit|kb-button)[^\"\"]*("|\")[^>]*>)/';
        if (preg_match($pattern, $block_content)) {
            return preg_replace($pattern, $turnstile_field . '$1', $block_content, 1);
        }

        // Fallback: inject before the closing form tag.
        $pos = strripos($block_content, '</form>');
        if ($pos !== false) {
            return substr_replace($block_content, $turnstile_field, $pos, 0);
        }

        return $block_content;
    }

    // Kadence Blocks PRO Contact Form Submission Check
    add_action('kadence_blocks_form_verify_nonce', 'cfturnstile_kadence_check', 10, 1);
    function cfturnstile_kadence_check($nonce) {

        if (cfturnstile_whitelisted()) {
            return $nonce;
        }

		$check = cfturnstile_check();
        $success = $check['success'];
        if ($success != true) {
            wp_die(cfturnstile_failed_message());
        }

        return $nonce;

    }
    
}