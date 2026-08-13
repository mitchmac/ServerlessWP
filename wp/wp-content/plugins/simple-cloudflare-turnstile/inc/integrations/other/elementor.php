<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if(get_option('cfturnstile_elementor')) {

  // Always load scripts globally (with scope controls).
  add_action('wp_enqueue_scripts', 'cfturnstile_elementor_enqueue_scripts', 99);

  function cfturnstile_elementor_enqueue_scripts( $ignore_scope = false ) {

    // Determine scope for global loading: 'all' | 'autodetect' | 'specific'
    if ( ! $ignore_scope ) {
      $scope = get_option('cfturnstile_elementor_global_scope', '');
      if ($scope === '') { $scope = 'all'; }

      // Always allow in Elementor preview/editor
      $is_builder = ( isset($_GET['elementor-preview']) || ( defined('ELEMENTOR_VERSION') && isset($_GET['action']) && $_GET['action'] === 'elementor' ) );

      if ($scope === 'specific' && !$is_builder) {
        $ids_raw = (string) get_option('cfturnstile_elementor_global_pages', '');
        $ids = array_filter( array_map( 'absint', array_map( 'trim', explode( ',', $ids_raw ) ) ) );
        $current_id = function_exists('get_queried_object_id') ? get_queried_object_id() : 0;
        if ( empty($ids) || !$current_id || !in_array( $current_id, $ids, true ) ) {
          return; // Not an allowed page
        }
      } elseif ($scope === 'autodetect' && !$is_builder) {
        $current_id = function_exists('get_queried_object_id') ? get_queried_object_id() : 0;
        $has_form = $current_id && cfturnstile_elementor_page_has_form($current_id);
        // Also check if any Elementor popups with forms might be triggered on this page
        if ( !$has_form ) {
          $has_form = cfturnstile_elementor_any_popup_has_form($current_id);
        }
        if ( !$has_form ) {
          return; // No form detected
        }
      }
    }
    
    // Determine failsafe mode for Elementor (keeps UI and backend in sync)
    $failsafe_mode = '';
    if ( get_option('cfturnstile_failover') && function_exists('cfturnstile_is_cloudflare_down') && cfturnstile_is_cloudflare_down() ) {
      $failsafe_mode = get_option('cfturnstile_failsafe_type', 'allow');
      if ( $failsafe_mode !== 'recaptcha' && $failsafe_mode !== 'allow' ) {
        $failsafe_mode = 'allow';
      }
      if ( $failsafe_mode === 'recaptcha' ) {
        $defer = get_option('cfturnstile_defer_scripts', 1) ? array('strategy' => 'defer') : array();
        if (!wp_script_is('cfturnstile-recaptcha', 'enqueued')) {
          wp_enqueue_script('cfturnstile-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, $defer);
        }
      }
    }

    // Enqueue Turnstile API script (only when not in failsafe UI mode)
    if ( $failsafe_mode === '' && !wp_script_is('cfturnstile', 'enqueued')) {
      $defer = get_option('cfturnstile_defer_scripts', 1) ? array('strategy' => 'defer') : array();
      cfturnstile_register_api($defer);
      wp_enqueue_script('cfturnstile');
    }
    
    // Enqueue our custom Elementor integration script
    if (!wp_script_is('cfturnstile-elementor-forms', 'enqueued')) {
      $deps = array('jquery');
      if ( $failsafe_mode === '' ) {
        $deps[] = 'cfturnstile';
      }

      // Load the interaction-only label helper if needed and make it a dependency
      if ( get_option('cfturnstile_widget_label_enable', 0) && get_option('cfturnstile_appearance', 'always') === 'interaction-only' ) {
        if ( !wp_script_is('cfturnstile-label-js', 'enqueued') ) {
          wp_enqueue_script('cfturnstile-label-js', plugins_url('simple-cloudflare-turnstile/js/interaction-label.js'), array(), '1.0', true);
        }
        $deps[] = 'cfturnstile-label-js';
      }

      wp_enqueue_script(
        'cfturnstile-elementor-forms',
        plugins_url('simple-cloudflare-turnstile/js/integrations/elementor-forms.js'),
        $deps,
        '2.8',
        true
      );

      // Resolve widget label text
      $label_enable = get_option('cfturnstile_widget_label_enable', 0) ? true : false;
      $label_text = get_option('cfturnstile_widget_label_text');
      $label_text = is_string($label_text) ? trim($label_text) : '';
      if ($label_text === '') {
        $label_text = __('Let us know you are human:', 'simple-cloudflare-turnstile');
      } else {
        $label_text = wp_strip_all_tags($label_text);
      }

      // Pass settings to JavaScript
      wp_localize_script('cfturnstile-elementor-forms', 'cfturnstileElementorSettings', array(
        'sitekey' => get_option('cfturnstile_key'),
        'position' => get_option('cfturnstile_elementor_pos', 'before'),
        'align' => get_option('cfturnstile_elementor_align', 'left'),
        'theme' => get_option('cfturnstile_theme'),
        'size' => get_option('cfturnstile_size', 'normal'),
        'appearance' => get_option('cfturnstile_appearance', 'always'),
        'mode' => $failsafe_mode ? $failsafe_mode : 'turnstile',
        'recaptchaSiteKey' => get_option('cfturnstile_recaptcha_site_key'),
        'disableSubmit' => get_option('cfturnstile_disable_button') ? true : false,
        'labelEnable' => $label_enable,
        'labelText' => $label_text
      ));
    }
  }

  /**
   * Detect if a given page contains Elementor Form or Login widgets.
   * @param int $post_id
   * @return bool
   */
  function cfturnstile_elementor_page_has_form($post_id){
    if (!$post_id) return false;
    $data = get_post_meta($post_id, '_elementor_data', true);
    if (empty($data)) return false;
    if (is_string($data)) {
      $json = json_decode($data, true);
    } else {
      $json = $data;
    }
    if (!is_array($json)) return false;
    return cfturnstile_elementor_elements_contain_form($json);
  }

  function cfturnstile_elementor_elements_contain_form($elements){
    foreach((array)$elements as $el){
      if (is_array($el)) {
        if (isset($el['elType']) && $el['elType'] === 'widget' && isset($el['widgetType'])){
          $wt = $el['widgetType'];
          if ($wt === 'form' || $wt === 'login') return true;
        }
        if (!empty($el['elements']) && is_array($el['elements'])){
          if (cfturnstile_elementor_elements_contain_form($el['elements'])) return true;
        }
      }
    }
    return false;
  }

  /**
   * Check if any published Elementor popup templates contain a form.
   * Also checks if the current page references any popup (via action triggers in Elementor data).
   * @param int $current_page_id
   * @return bool
   */
  function cfturnstile_elementor_any_popup_has_form($current_page_id = 0) {
    // Check if Elementor Pro popup post type exists
    if ( !post_type_exists('elementor_library') ) {
      return false;
    }

    // First, try to detect popup IDs referenced in the current page's Elementor data
    $popup_ids = array();
    if ( $current_page_id ) {
      $data = get_post_meta($current_page_id, '_elementor_data', true);
      if ( !empty($data) ) {
        $data_str = is_string($data) ? $data : wp_json_encode($data);
        // Elementor stores popup action triggers like "popup_id":"123"
        if ( preg_match_all('/\"popup_id\"\s*:\s*\"(\d+)\"/', $data_str, $matches) ) {
          $popup_ids = array_map('absint', $matches[1]);
        }
      }
    }

    // If we found specific popup references, only check those
    if ( !empty($popup_ids) ) {
      foreach ( $popup_ids as $popup_id ) {
        if ( cfturnstile_elementor_page_has_form($popup_id) ) {
          return true;
        }
      }
      return false;
    }

    // Fallback: query all published popup templates for forms
    $popups = get_posts(array(
      'post_type'      => 'elementor_library',
      'posts_per_page' => 50,
      'post_status'    => 'publish',
      'meta_query'     => array(
        array(
          'key'   => '_elementor_template_type',
          'value' => 'popup',
        ),
      ),
      'fields' => 'ids',
    ));
    if ( !empty($popups) ) {
      foreach ( $popups as $popup_id ) {
        if ( cfturnstile_elementor_page_has_form($popup_id) ) {
          return true;
        }
      }
    }
    return false;
  }
  
  // Elementor Forms Check
  add_action('elementor_pro/forms/validation', 'cfturnstile_elementor_check', 10, 2);
  function cfturnstile_elementor_check($record, $ajax_handler){
    if(!cfturnstile_whitelisted()) {
      $error_message = cfturnstile_failed_message();
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    $check = cfturnstile_check();
    $success = $check['success'];
    if($success != true) {
      $ajax_handler->add_error_message( $error_message );
      $ajax_handler->add_error( '', '' );
      $ajax_handler->is_success = false;
    }
    } else {
    $ajax_handler->add_error_message( $error_message );
    $ajax_handler->add_error( '', '' );
    $ajax_handler->is_success = false;
    }
    }
  }

}