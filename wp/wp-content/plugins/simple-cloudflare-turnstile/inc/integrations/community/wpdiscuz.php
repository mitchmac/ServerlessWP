<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if(get_option('cfturnstile_comment')) {

  add_action('wpdiscuz_before_comments','cfturnstile_field_wpdiscuz_script');
  function cfturnstile_field_wpdiscuz_script() {
      do_action("cfturnstile_enqueue_scripts");
  }

  add_action('wpdiscuz_submit_button_before','cfturnstile_field_wpdiscuz', 10, 3);
  function cfturnstile_field_wpdiscuz( $currentUser, $uniqueId, $isMainForm ) {
      $uniqueId = sanitize_text_field($uniqueId);
      $turnstilecode = '<div id="cf-turnstile-wpd-'.esc_html($uniqueId).'" class="wpdiscuz-cfturnstile" style="margin-left: -2px; margin-top: 10px; margin-bottom: 10px; display: inline-flex;"></div><div style="clear: both;"></div>';
    	$appearance = esc_attr(get_option('cfturnstile_appearance', 'always'));
      $cfturnstile_size = esc_attr(get_option('cfturnstile_size'), 'normal');
      ?>
      <script>
      jQuery(document).ready(function() {
          <?php if($uniqueId == "0_0") { ?>
          jQuery('.wpd_main_comm_form .wpd-form-col-right .wc-field-submit').before(<?php echo wp_json_encode( $turnstilecode ); ?>);
          <?php } else { ?>
          jQuery('#wpd-comm-<?php echo esc_js($uniqueId); ?> .wpd-form-col-right .wc-field-submit').before(<?php echo wp_json_encode( $turnstilecode ); ?>);
          <?php } ?>
          var cftContainer_<?php echo esc_html(str_replace(array('-', '_'), '', $uniqueId)); ?> = document.getElementById('cf-turnstile-wpd-<?php echo esc_html($uniqueId); ?>');
          if (cftContainer_<?php echo esc_html(str_replace(array('-', '_'), '', $uniqueId)); ?>) {
            var cftWidgetId_<?php echo esc_html(str_replace(array('-', '_'), '', $uniqueId)); ?> = turnstile.render(cftContainer_<?php echo esc_html(str_replace(array('-', '_'), '', $uniqueId)); ?>, {
              sitekey: '<?php echo sanitize_text_field( get_option('cfturnstile_key') ); ?>',
              appearance: '<?php echo sanitize_text_field($appearance); ?>',
              size: '<?php echo sanitize_text_field($cfturnstile_size); ?>',
              action: 'wpdiscuz-comment',
              <?php if(get_option('cfturnstile_disable_button')) { ?>
              callback: function(token) {
                jQuery('#wpd-field-submit-<?php echo esc_html($uniqueId); ?>').css('pointer-events', 'auto');
                jQuery('#wpd-field-submit-<?php echo esc_html($uniqueId); ?>').css('opacity', '1');
              },
              <?php } ?>
            });
          }
      });
      jQuery( document ).ready(function() {
        jQuery( '#wpd-field-submit-<?php echo esc_html($uniqueId); ?>' ).click(function(){
          if (typeof cftWidgetId_<?php echo esc_html(str_replace(array('-', '_'), '', $uniqueId)); ?> !== 'undefined') {
            setTimeout(function() {
              turnstile.reset(cftWidgetId_<?php echo esc_html(str_replace(array('-', '_'), '', $uniqueId)); ?>);
            }, 2000);
          }
        });
      });
      jQuery(document).ajaxComplete(function() {
        setTimeout(function() {
          var cftEl = document.getElementById('cf-turnstile-wpd-<?php echo esc_js($uniqueId); ?>');
          if (cftEl) {
            turnstile.remove('#cf-turnstile-wpd-<?php echo esc_js($uniqueId); ?>');
            cftWidgetId_<?php echo esc_html(str_replace(array('-', '_'), '', $uniqueId)); ?> = turnstile.render(cftEl, {
              sitekey: '<?php echo esc_js( sanitize_text_field( get_option('cfturnstile_key') ) ); ?>',
              appearance: '<?php echo esc_js($appearance); ?>',
              size: '<?php echo esc_js($cfturnstile_size); ?>',
              action: 'wpdiscuz-comment',
              <?php if(get_option('cfturnstile_disable_button')) { ?>
              callback: function(token) {
                jQuery('#wpd-field-submit-<?php echo esc_js($uniqueId); ?>').css('pointer-events', 'auto');
                jQuery('#wpd-field-submit-<?php echo esc_js($uniqueId); ?>').css('opacity', '1');
              },
              <?php } ?>
            });
            <?php if(get_option('cfturnstile_disable_button')) { ?>
            jQuery('#wpd-field-submit-<?php echo esc_js($uniqueId); ?>').css('pointer-events', 'none').css('opacity', '0.5');
            <?php } ?>
          }
        }, 1000);
      });
      </script>
      <?php if(get_option('cfturnstile_disable_button')) { ?>
    	<style>#wpd-field-submit-<?php echo esc_html($uniqueId); ?> { pointer-events: none; opacity: 0.5; }</style>
      <?php } ?>
      <?php
  }

  // Validate wpDiscuz comments via preprocess_comment filter
  // This filter can actually block submissions, unlike the action hook
  add_filter('preprocess_comment', 'cfturnstile_wpdiscuz_check', 9);
  function cfturnstile_wpdiscuz_check( $comment_data ) {
    // Check if this is a wpDiscuz AJAX request
    $action = isset($_POST['action']) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
    
    // Only process wpDiscuz comment submissions
    if ( ! ( 'wpdAddComment' === $action && wp_doing_ajax() ) ) {
      return $comment_data;
    }
    
    // Skip if whitelisted
    if ( cfturnstile_whitelisted() ) {
      return $comment_data;
    }
    
    // Verify Turnstile
    $check = cfturnstile_check();
    $success = ( is_array($check) && isset($check['success']) ) ? $check['success'] : false;
    
    if ( $success !== true ) {
      wp_die( esc_html( cfturnstile_failed_message() ) );
    }
    
    return $comment_data;
  }

}
