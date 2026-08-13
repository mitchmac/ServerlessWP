<?php
if (!defined("ABSPATH")) {
    exit();
}

if (get_option("cfturnstile_jetpack")) {
    add_filter(
        "jetpack_contact_form_html",
        "cfturnstile_field_jetpack_form",
        10,
        1
    );
    function cfturnstile_field_jetpack_form($html)
    {
        ob_start();
        $unique_id = wp_rand();

        $button_id = ".wp-block-jetpack-contact-form button";
        ob_start();
        cfturnstile_field_show(
            $button_id,
            "turnstileJetpackCallback",
            "jetpack-form",
            "-jetpack-" . $unique_id
        );
        $cfturnstile = ob_get_contents();
        ob_end_clean();
        wp_reset_postdata();

        // If '<button class="wp-block-button__link" style="" data-id-attr="placeholder" type="submit">' exists in $html
        if(strpos($html, '<button class="wp-block-button__link" style="" data-id-attr="placeholder" type="submit">') !== false) {
            $position = strpos($html, '<button class="wp-block-button__link" style="" data-id-attr="placeholder" type="submit">');
        } else {
            $position = strpos($html, "</form>");
        }

        if ($position !== false) {
            $html = substr_replace($html, $cfturnstile, $position, 0);
        }

        return $html;
    }

    add_filter(
        "jetpack_contact_form_is_spam",
        "cfturnstile_jetpack_check",
        10,
        1
    );
    function cfturnstile_jetpack_check($default)
    {
        $check = cfturnstile_check();
        $success = $check["success"];
        if (!$success) {
            $error_message = cfturnstile_failed_message();
            
            // Modify the contact form HTML.
            add_filter("jetpack_contact_form_html", function ($format_html) use ($error_message) {
                // Display the error message.
                $error_html = '<div class="contact-form__error">' .
                    esc_html($error_message) .
                    "</div>";
                
                // Persist field values.
                $dom = new DOMDocument();
                @$dom->loadHTML($format_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                $inputs = $dom->getElementsByTagName('input');

                foreach ($inputs as $input) {
                    $name = $input->getAttribute('name');
                    if (isset($_POST[$name])) {
                        $input->setAttribute('value', esc_attr($_POST[$name]));
                    }
                }

                $textareas = $dom->getElementsByTagName('textarea');
                foreach ($textareas as $textarea) {
                    $name = $textarea->getAttribute('name');
                    if (isset($_POST[$name])) {
                        $textarea->nodeValue = esc_html($_POST[$name]);
                    }
                }

                // Return the modified HTML.
                return $error_html . $dom->saveHTML();
            });

            // Return a custom error to abort the form process submission.
            return new WP_Error("captcha_failed", $error_message);
        }
        return $default || !$success;
    }

}