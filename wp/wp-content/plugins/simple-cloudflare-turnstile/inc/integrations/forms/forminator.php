<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if(get_option('cfturnstile_forminator')) {

	// Get turnstile field: Forminator Forms
	add_filter( 'forminator_render_form_submit_markup', 'cfturnstile_field_forminator_form', 10, 4 );
	function cfturnstile_field_forminator_form( $html, $form_id, $post_id, $nonce ) {

        if(!cfturnstile_form_disable($form_id, 'cfturnstile_forminator_disable')) {

            ob_start();

            // Determine failsafe UI mode (keeps UI behavior consistent with backend validation)
            $failsafe_mode = '';
            if ( get_option('cfturnstile_failover') && function_exists('cfturnstile_is_cloudflare_down') && cfturnstile_is_cloudflare_down() ) {
                $failsafe_mode = get_option('cfturnstile_failsafe_type', 'allow');
                if ( $failsafe_mode !== 'recaptcha' && $failsafe_mode !== 'allow' ) {
                    $failsafe_mode = 'allow';
                }
            }

            // Only load Turnstile API in normal mode. In failsafe mode, cfturnstile_field_show()
            // renders a marker or reCAPTCHA instead, so Turnstile JS would be unnecessary (and can error).
            if ( $failsafe_mode === '' ) {
                // if cfturnstile script doesnt exist, enqueue it
                if(!wp_script_is('cfturnstile', 'enqueued')) {
                    cfturnstile_register_api(true);
                    wp_print_scripts('cfturnstile');
                }
            }
            echo "<style>#cf-turnstile-fmntr-".esc_html($form_id)." { margin-left: 0px !important; }</style>";

            cfturnstile_field_show('.forminator-button-submit', 'turnstileForminatorCallback', 'forminator-form-' . esc_html($form_id), '-fmntr-' . esc_html($form_id));

            // If failsafe reCAPTCHA is used, ensure the script tag is printed even when the form is
            // loaded via AJAX (wp_enqueue_script alone may not output in the AJAX response).
            if ( $failsafe_mode === 'recaptcha' && wp_script_is('cfturnstile-recaptcha', 'enqueued') && !wp_script_is('cfturnstile-recaptcha', 'done') ) {
                wp_print_scripts('cfturnstile-recaptcha');
            }
            ?>
            <?php if ( $failsafe_mode === '' ) { ?>
            <script>
            // Explicit rendering ignores data-*-callback, which would leave the submit button disabled.
            function cfturnstileForminatorOpts(target) {
                return (typeof window.cfturnstileOpts === 'function') ? window.cfturnstileOpts(target) : {};
            }
            // On ajax.complete run turnstile.render if element is empty
            jQuery(document).ajaxComplete(function() {
                setTimeout(function() {
                    if (document.getElementById('cf-turnstile-fmntr-<?php echo esc_html($form_id); ?>')) {
                        if(!document.getElementById('cf-turnstile-fmntr-<?php echo esc_html($form_id); ?>').innerHTML.trim()) {
                                turnstile.remove('#cf-turnstile-fmntr-<?php echo esc_html($form_id); ?>');
                                turnstile.render('#cf-turnstile-fmntr-<?php echo esc_html($form_id); ?>', cfturnstileForminatorOpts('#cf-turnstile-fmntr-<?php echo esc_html($form_id); ?>'));
                        }
                    }
                }, 1000);
            });
            // Enable Submit Button Function
            function turnstileForminatorCallback() {
                document.querySelectorAll('.forminator-button, .forminator-button-submit').forEach(function(el) {
                    el.style.pointerEvents = 'auto';
                    el.style.opacity = '1';
                });
            }
            // On submit re-render
            jQuery(document).ready(function() {
                jQuery('.forminator-custom-form').on('submit', function() {
                    if(document.getElementById('cf-turnstile-fmntr-<?php echo esc_html($form_id); ?>')) {
                        setTimeout(function() {
                            turnstile.remove('#cf-turnstile-fmntr-<?php echo esc_html($form_id); ?>');
                            turnstile.render('#cf-turnstile-fmntr-<?php echo esc_html($form_id); ?>', cfturnstileForminatorOpts('#cf-turnstile-fmntr-<?php echo esc_html($form_id); ?>'));
                        }, 1000);
                    }
                });
            });
            </script>
            <?php } ?>
            <?php
            $cfturnstile = ob_get_contents();
            ob_end_clean();
            wp_reset_postdata();

            if(!empty(get_option('cfturnstile_forminator_pos')) && get_option('cfturnstile_forminator_pos') == "after") {
                return $html . $cfturnstile;
            } else {
                return $cfturnstile . $html;
            }

        } else {
            return $html;
        }

	}

	// Forminator Forms Check
	add_action('forminator_custom_form_submit_errors', 'cfturnstile_forminator_check', 10, 3);
	function cfturnstile_forminator_check($submit_errors, $form_id, $field_data_array){
        // Forminator runs its error check several times for a single submission (once from
        // prepare_fields_info(), again after file upload handling, again for subscription
        // payment intents). Remember the passes made in this request so the repeats never
        // re-verify a token that has already been spent.
        static $verified_tokens = array();

        if(!cfturnstile_form_disable($form_id, 'cfturnstile_forminator_disable')) {

            // Normalize Forminator's field data (provided in several shapes) so we can
            // read the Turnstile token from the submission.
            $posted_data = array();
            if (is_array($field_data_array)) {
                foreach ($field_data_array as $key => $val) {
                    // Sometimes Forminator provides an associative array of name => value
                    if (is_string($key) && !is_array($val) && !is_object($val)) {
                        $posted_data[$key] = $val;
                        continue;
                    }
                    // Sometimes it provides an array of arrays with keys like ['name' => ..., 'value' => ...]
                    if (is_array($val) && isset($val['name'])) {
                        $name = $val['name'];
                        $value = array_key_exists('value', $val) ? $val['value'] : '';
                        if (is_string($name)) {
                            $posted_data[$name] = $value;
                        }
                        continue;
                    }
                    // Or an array of objects with ->name and ->value
                    if (is_object($val) && isset($val->name)) {
                        $name = $val->name;
                        $value = isset($val->value) ? $val->value : '';
                        if (is_string($name)) {
                            $posted_data[$name] = $value;
                        }
                    }
                }
            }

            $token = '';
            if (isset($posted_data['cf-turnstile-response']) && !is_array($posted_data['cf-turnstile-response'])) {
                $token = sanitize_text_field($posted_data['cf-turnstile-response']);
            }

            // Fallback: if the token was not present in the structured field data
            if ($token === '' && isset($_POST['cf-turnstile-response']) && !is_array($_POST['cf-turnstile-response'])) {
                $token = sanitize_text_field($_POST['cf-turnstile-response']);
            }

            // The pass is cached against the single-use token itself, never a client-supplied
            // form_uid, so it can only ever be re-served for the token actually verified.
            $memo_key     = $form_id . '|' . $token;
            $verified_key = 'cfturnstile_forminator_' . $form_id;
            $verified_ttl = 10;

            // Repeat calls within this request cost the token nothing.
            if ($token !== '' && isset($verified_tokens[$memo_key])) {
                return $submit_errors;
            }

            // Cross-request fallback, for the flows that re-submit the same token in a follow-up
            // request. Consumed on read, so a solved challenge survives exactly one extra request
            // rather than being replayable for the whole lifetime of the transient.
            if ($token !== '' && cfturnstile_get_verified($verified_key, $token)) {
                cfturnstile_clear_verified($verified_key, $token);
                $verified_tokens[$memo_key] = true;
                return $submit_errors;
            }

            $_post_backup = array();
            $sync_keys = array(
                'cf-turnstile-response',
                'cfturnstile_failsafe',
                'g-recaptcha-response',
            );
            foreach ($sync_keys as $sync_key) {
                $_post_backup[$sync_key] = array_key_exists($sync_key, $_POST) ? $_POST[$sync_key] : null;
                if (isset($posted_data[$sync_key]) && !is_array($posted_data[$sync_key])) {
                    $_POST[$sync_key] = sanitize_text_field($posted_data[$sync_key]);
                }
            }

            // Ensure the resolved token is available in $_POST for cfturnstile_check()
            // and any failover logic that reads from $_POST.
            if ($token !== '') {
                $_POST['cf-turnstile-response'] = $token;
            }

            $check = cfturnstile_check($token);
            foreach ($_post_backup as $sync_key => $old_val) {
                if ($old_val === null) {
                    unset($_POST[$sync_key]);
                } else {
                    $_POST[$sync_key] = $old_val;
                }
            }

            $success = (is_array($check) && isset($check['success'])) ? $check['success'] : false;
            if($success != true) {
                $submit_errors[]['submit'] = cfturnstile_failed_message();
            } elseif ($token !== '') {
                $verified_tokens[$memo_key] = true;
                cfturnstile_set_verified($verified_key, $token, $verified_ttl);
            }
        }
        return $submit_errors;
	}

}