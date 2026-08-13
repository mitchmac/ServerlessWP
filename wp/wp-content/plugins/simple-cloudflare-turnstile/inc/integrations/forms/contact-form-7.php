<?php
if (!defined('ABSPATH')) {
	exit;
}

// Create shortcode
add_shortcode('cf7-simple-turnstile', 'cfturnstile_cf7_shortcode');
add_filter('wpcf7_form_elements', 'cfturnstile_cf7_do_shortcode');
function cfturnstile_cf7_do_shortcode($content) {
	if (is_string($content) && false !== strpos($content, '[cf7-simple-turnstile]')) {
		return do_shortcode($content);
	}
	return $content;
}
function cfturnstile_cf7_shortcode() {
	// If user is whitelisted, render nothing so CF7's form HTML is untouched.
	if (function_exists('cfturnstile_whitelisted') && cfturnstile_whitelisted()) {
		return '';
	}
	ob_start();
	$id = wp_rand();
	echo '<div class="cf7-cf-turnstile" style="margin-top: 0px; margin-bottom: -15px;">';
	echo cfturnstile_field_show('.wpcf7-submit', 'turnstileCF7Callback', 'contact-form-7', '-cf7-' . $id);
	?>
	<script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll('.wpcf7-form').forEach(function(e){e.addEventListener('submit',function(){if(document.getElementById('cf-turnstile-cf7-<?php echo esc_js( $id ); ?>')){setTimeout(function(){turnstile.reset('#cf-turnstile-cf7-<?php echo esc_js( $id ); ?>');},1000)}})})});</script>
	<?php
	$disable_script = "function turnstileCF7Callback() {
    document.querySelectorAll('.wpcf7-submit').forEach(function(el) {
        el.style.pointerEvents = 'auto';
        el.style.opacity = '1';
    });
	}";
	wp_add_inline_script('cfturnstile', $disable_script);
	echo '</div>';
	$thecontent = ob_get_contents();
	ob_end_clean();
	wp_reset_postdata();
	$thecontent = trim(preg_replace('/\s+/', ' ', $thecontent));
	return $thecontent;
}

// Add Turnstile to all CF7 forms at once.
if ((!empty(get_option('cfturnstile_cf7_all')) && get_option('cfturnstile_cf7_all'))) {
	add_action('wpcf7_form_elements', 'cfturnstile_field_cf7', 10, 1);
	function cfturnstile_field_cf7($content) {
		// If user is whitelisted, leave the CF7 form HTML untouched.
		if (function_exists('cfturnstile_whitelisted') && cfturnstile_whitelisted()) {
			return $content;
		}
		// If the form already uses [cf7-simple-turnstile] (or its rendered widget is present), don't inject again.
		if (class_exists('WPCF7_ContactForm')) {
			$current_form = WPCF7_ContactForm::get_current();
			if ($current_form) {
				$form_def = $current_form->prop('form');
				if (is_string($form_def) && false !== strpos($form_def, '[cf7-simple-turnstile]')) {
					return $content;
				}
			}
		}
		$cfturnstile_key = sanitize_text_field(get_option('cfturnstile_key'));
		if (false === strpos($content, $cfturnstile_key)) {
			$widget = cfturnstile_cf7_shortcode() . '<br/>';
			$replaced = preg_replace('/(<(?:input|button)[^>]*type="submit")/i', $widget . '$1', $content, 1, $count);
			if ($count > 0) {
				return $replaced;
			}
			return preg_replace('/(<button\b(?![^>]*type=)[^>]*)/i', $widget . '$1', $content, 1);
		} else {
			return $content;
		}
	}
}

// Validate form submission
add_filter('wpcf7_validate', 'cfturnstile_cf7_verify_recaptcha', 20, 2);
function cfturnstile_cf7_verify_recaptcha($result) {

	if (!class_exists('WPCF7_Submission')) {
		return $result;
	}

	// Prevent duplicate execution within a single request.
	static $cfturnstile_cf7_ran = false;
	if ( $cfturnstile_cf7_ran ) {
		return $result;
	}

	$post = WPCF7_Submission::get_instance();

	$_wpcf7 = !empty($_POST['_wpcf7']) ? absint($_POST['_wpcf7']) : 0;

	if (!empty($post)) {

		// Skip entirely for whitelisted users so we never invalidate their submission.
		if (cfturnstile_whitelisted()) {
			return $result;
		}

		$data = $post->get_posted_data();

		// Check if "Enable on all CF7 Forms" option is enabled
		$cf7_all_enabled = !empty(get_option('cfturnstile_cf7_all')) && get_option('cfturnstile_cf7_all');
		
		// Check if the form contains our shortcode [cf7-simple-turnstile]
		$form_has_shortcode = false;
		if ($_wpcf7 && class_exists('WPCF7_ContactForm')) {
			$contact_form = WPCF7_ContactForm::get_instance($_wpcf7);
			if ($contact_form) {
				$form_content = $contact_form->prop('form');
				$form_has_shortcode = (false !== strpos($form_content, '[cf7-simple-turnstile]'));
			}
		}
		
		// Only validate if SCT is enabled for this form (either via "all forms" option or shortcode)
		if (!$cf7_all_enabled && !$form_has_shortcode) {
			return $result;
		}

		$message = cfturnstile_failed_message();

		$token = isset($data['cf-turnstile-response']) ? $data['cf-turnstile-response'] : '';
		$check = cfturnstile_check($token);
		$success = $check['success'];
		$cfturnstile_cf7_ran = true;
		if ($success != true) {
			$result->invalidate(array('type' => 'captcha', 'name' => 'cf-turnstile'), $message);
			$GLOBALS['cfturnstile_cf7_failed_message'] = $message;
			return $result;
		}
		
	}

	return $result;
}

// Replace CF7's generic "One or more fields have an error" with the Turnstile-specific message.
add_filter('wpcf7_display_message', 'cfturnstile_cf7_display_message', 10, 2);
function cfturnstile_cf7_display_message($message, $status) {
	if ('validation_error' === $status && !empty($GLOBALS['cfturnstile_cf7_failed_message'])) {
		return $GLOBALS['cfturnstile_cf7_failed_message'];
	}
	return $message;
}

// Add form tag
add_action('wpcf7_init', 'cfturnstile_cf7_add_form_tag_button', 10, 0);
function cfturnstile_cf7_add_form_tag_button() {
	wpcf7_add_form_tag('cf7-simple-turnstile', 'cfturnstile_cf7_shortcode');
}

// Form tag generator
add_action('wpcf7_admin_init', 'cfturnstile_cf7_add_tag_generator_button', 55, 0);
function cfturnstile_cf7_add_tag_generator_button() {
	$tag_generator = WPCF7_TagGenerator::get_instance();
	$tag_generator->add('cf7-simple-turnstile', esc_html__('cloudflare turnstile', 'contact-form-7'), 'cfturnstile_cf7_tag_generator_button', array( 'version' => '2' ) );
}

// Insert tag form
function cfturnstile_cf7_tag_generator_button($contact_form, $args = '') {
	$args = wp_parse_args($args, array());
	?>
	<div class="insert-box">
		<input type="text" name="cf7-simple-turnstile" class="tag code" readonly="readonly" onfocus="this.select()" />
		<div class="submitbox">
			<input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr(__('Insert Tag', 'contact-form-7')); ?>" />
		</div>
	</div>
<?php
}