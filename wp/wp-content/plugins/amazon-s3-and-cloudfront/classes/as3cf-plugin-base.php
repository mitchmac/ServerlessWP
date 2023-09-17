<?php

use DeliciousBrains\WP_Offload_Media\Settings_Interface;
use DeliciousBrains\WP_Offload_Media\Settings_Trait;

abstract class AS3CF_Plugin_Base implements Settings_Interface {
	use Settings_Trait;

	const DBRAINS_URL = 'https://deliciousbrains.com';
	const WPE_URL     = 'https://wpengine.com';

	const SETTINGS_KEY = '';

	/**
	 * @var array
	 */
	protected static $settings_constants = array();

	protected static $plugin_page = 'amazon-s3-and-cloudfront';

	protected $plugin_file_path;
	protected $plugin_dir_path;
	protected $plugin_slug;
	protected $plugin_basename;
	protected $plugin_version;
	protected $plugin_name;

	/**
	 * Initiate plugin
	 *
	 * @param string $plugin_file_path
	 */
	function __construct( $plugin_file_path ) {
		$this->plugin_file_path = $plugin_file_path;
		$this->plugin_dir_path  = rtrim( plugin_dir_path( $plugin_file_path ), '/' );
		$this->plugin_basename  = plugin_basename( $plugin_file_path );

		if ( $this->plugin_slug && isset( $GLOBALS['aws_meta'][ $this->plugin_slug ]['version'] ) ) {
			$this->plugin_version = $GLOBALS['aws_meta'][ $this->plugin_slug ]['version'];
		}

		$plugin_headers = array();

		if ( is_admin() ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin_headers = get_plugin_data( $plugin_file_path, false, false );
		}

		// Fallback to generic plugin name if it can't be retrieved from the plugin headers.
		$this->plugin_name = empty( $plugin_headers['Name'] ) ? 'WP Offload Media' : $plugin_headers['Name'];
	}

	/**
	 * Accessor for plugin name.
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Accessor for plugin version
	 *
	 * @return mixed
	 */
	public function get_plugin_version() {
		return $this->plugin_version;
	}

	/**
	 * Accessor for plugin slug
	 *
	 * @return string
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Accessor for plugin basename
	 *
	 * @return string
	 */
	public function get_plugin_basename() {
		return $this->plugin_basename;
	}

	/**
	 * Accessor for plugin file path
	 *
	 * @return string
	 */
	public function get_plugin_file_path() {
		return $this->plugin_file_path;
	}

	/**
	 * Accessor for plugin dir path
	 *
	 * @return string
	 */
	public function get_plugin_dir_path() {
		return $this->plugin_dir_path;
	}

	/**
	 * Accessor for plugin sdks dir path
	 *
	 * @return string
	 */
	public function get_plugin_sdks_dir_path() {
		return $this->get_plugin_dir_path() . '/vendor';
	}

	/**
	 * Which page name should we use?
	 *
	 * @return string
	 */
	public static function get_plugin_pagenow() {
		return is_multisite() ? 'settings.php' : 'options-general.php';
	}

	/**
	 * Get a specific setting.
	 *
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function get_setting( string $key, $default = '' ) {
		$this->get_settings();

		if ( isset( $this->settings[ $key ] ) ) {
			$setting = $this->settings[ $key ];
		} else {
			$setting = $default;
		}

		/**
		 * Filter the setting value retrieved for the given key.
		 *
		 * @param mixed  $setting
		 * @param string $key
		 */
		return apply_filters( 'as3cf_get_setting', $setting, $key );
	}

	/**
	 * Get a specific setting from the core plugin.
	 *
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function get_core_setting( string $key, $default = '' ) {
		return $this->get_setting( $key, $default );
	}

	/**
	 * Add or overwrite new style defined values with legacy value.
	 *
	 * Should be overridden by concrete plugin classes.
	 *
	 * @param array $defines
	 *
	 * @return array
	 */
	protected function get_legacy_defined_settings( array $defines ): array {
		return $defines;
	}

	/**
	 * Which defined settings keys are from the standard settings constant?
	 *
	 * @return array
	 */
	public function get_non_legacy_defined_settings_keys(): array {
		static $keys = null;

		if ( is_null( $keys ) ) {
			$keys = array_keys( array_diff_key( $this->get_defined_settings(), $this->get_legacy_defined_settings( array() ) ) );
		}

		return $keys;
	}

	/**
	 * Allowed settings keys for this plugin.
	 * Meant to be overridden in child classes.
	 *
	 * @param bool $include_legacy Should legacy keys be included? Optional, default false.
	 *
	 * @return array
	 */
	public function get_allowed_settings_keys( bool $include_legacy = false ): array {
		return array();
	}

	/**
	 * Get the settings that should not be shown once saved.
	 *
	 * @return array
	 */
	public function get_sensitive_settings(): array {
		return array();
	}

	/**
	 * Get the blacklisted settings for monitoring changes in defines.
	 * These settings will not be saved in the database.
	 * Meant to be overridden in child classes.
	 *
	 * @return array
	 */
	public function get_monitored_settings_blacklist(): array {
		return array();
	}

	/**
	 * List of settings that should skip full sanitize.
	 *
	 * @return array
	 */
	public function get_skip_sanitize_settings(): array {
		return array();
	}

	/**
	 * List of settings that should be treated as paths.
	 *
	 * @return array
	 */
	public function get_path_format_settings(): array {
		return array();
	}

	/**
	 * List of settings that should be treated as directory paths.
	 *
	 * @return array
	 */
	public function get_prefix_format_settings(): array {
		return array();
	}

	/**
	 * List of settings that should be treated as booleans.
	 *
	 * @return array
	 */
	public function get_boolean_format_settings(): array {
		return array();
	}

	/**
	 * Render a view template file
	 *
	 * @param string $view View filename without the extension
	 * @param array  $args Arguments to pass to the view
	 */
	public function render_view( $view, $args = array() ) {
		extract( $args );
		include $this->plugin_dir_path . '/view/' . $view . '.php';
	}

	/**
	 * Helper method to return the settings page URL for the plugin
	 *
	 * @param array  $args
	 * @param string $url_method To prepend to admin_url()
	 * @param bool   $escape     Should we escape the URL
	 *
	 * @return string
	 */
	public static function get_plugin_page_url( $args = array(), $url_method = 'network', $escape = true ) {
		$default_args = array(
			'page' => static::$plugin_page,
		);

		$args = array_merge( $default_args, $args );

		switch ( $url_method ) {
			case 'self':
				$base_url = self_admin_url( static::get_plugin_pagenow() );
				break;
			default:
				$base_url = network_admin_url( static::get_plugin_pagenow() );
		}

		// Add a hash to the URL
		$hash = false;
		if ( isset( $args['hash'] ) ) {
			$hash = $args['hash'];
			unset( $args['hash'] );
		}

		$url = add_query_arg( $args, $base_url );

		if ( $hash ) {
			$url .= '#' . $hash;
		}

		if ( $escape ) {
			$url = esc_url_raw( $url );
		}

		return $url;
	}

	/**
	 * The text for the plugin action link for the plugin on the plugins page.
	 *
	 * @return string
	 */
	function get_plugin_action_settings_text() {
		return __( 'Settings', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Add a link to the plugin row for the plugin on the plugins page.
	 * Needs to be implemented for an extending class using -
	 *     add_filter( 'plugin_action_links', array( $this, 'plugin_actions_settings_link' ), 10, 2 );
	 *
	 * @param array  $links
	 * @param string $file
	 *
	 * @return array
	 */
	function plugin_actions_settings_link( $links, $file ) {
		$url  = $this->get_plugin_page_url();
		$text = $this->get_plugin_action_settings_text();

		$settings_link = '<a href="' . $url . '">' . esc_html( $text ) . '</a>';

		if ( $file == $this->plugin_basename ) {
			array_unshift( $links, $settings_link );
		}

		return $links;
	}

	/**
	 * Enqueue script.
	 *
	 * @param string $handle
	 * @param string $path
	 * @param array  $deps
	 * @param bool   $footer
	 */
	public function enqueue_script( $handle, $path, $deps = array(), $footer = true ) {
		$version = $this->get_asset_version();
		$suffix  = $this->get_asset_suffix();

		$src = plugins_url( $path . $suffix . '.js', $this->plugin_file_path );
		wp_enqueue_script( $handle, $src, $deps, $version, $footer );
	}

	/**
	 * Enqueue style.
	 *
	 * @param string $handle
	 * @param string $path
	 * @param array  $deps
	 */
	public function enqueue_style( $handle, $path, $deps = array() ) {
		$version = $this->get_asset_version();

		$src = plugins_url( $path . '.css', $this->plugin_file_path );
		wp_enqueue_style( $handle, $src, $deps, $version );
	}

	/**
	 * Get the version used for script enqueuing
	 *
	 * @return mixed
	 */
	public function get_asset_version() {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : $this->plugin_version;
	}

	/**
	 * Get the filename suffix used for script enqueuing
	 *
	 * @return mixed
	 */
	public function get_asset_suffix() {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	}

	/**
	 * Generate Delicious Brains site URL with correct UTM tags.
	 *
	 * @param string $path
	 * @param array  $args
	 * @param string $hash
	 *
	 * @return string
	 */
	public static function dbrains_url( $path, $args = array(), $hash = '' ) {
		$args = wp_parse_args( $args, array(
			'utm_medium' => 'insideplugin',
			'utm_source' => static::get_utm_source(),
		) );
		$args = array_map( 'urlencode', $args );
		$url  = trailingslashit( self::DBRAINS_URL ) . ltrim( $path, '/' );
		$url  = add_query_arg( $args, $url );

		if ( $hash ) {
			$url .= '#' . $hash;
		}

		return $url;
	}

	/**
	 * Generate WP Engine site URL with correct UTM tags.
	 *
	 * @param string $path
	 * @param array  $args
	 * @param string $hash
	 *
	 * @return string
	 */
	public static function wpe_url( $path = '', $args = array(), $hash = '' ) {
		$args = wp_parse_args( $args, array(
			'utm_medium'   => 'referral',
			'utm_source'   => 'ome_plugin',
			'utm_campaign' => 'bx_prod_referral',
		) );
		$args = array_map( 'urlencode', $args );
		$url  = trailingslashit( self::WPE_URL ) . ltrim( $path, '/' );
		$url  = add_query_arg( $args, $url );

		if ( $hash ) {
			$url .= '#' . $hash;
		}

		return $url;
	}

	/**
	 * Get UTM source for plugin.
	 *
	 * @return string
	 */
	protected static function get_utm_source() {
		return 'AWS';
	}

	/**
	 * Get UTM content for WP Engine URL.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	protected static function get_wpe_url_utm_content( $content = 'plugin_footer_text' ) {
		return $content;
	}

	/**
	 * Get the My Account URL
	 *
	 * @param array  $args
	 * @param string $hash
	 *
	 * @return string
	 */
	public function get_my_account_url( $args = array(), $hash = '' ) {
		return $this->dbrains_url( '/my-account/', $args, $hash );
	}

	/**
	 * Sets up hooks to alter the footer of our admin pages.
	 *
	 * @return void
	 */
	protected function init_admin_footer() {
		add_filter( 'admin_footer_text', array( $this, 'filter_admin_footer_text' ) );
		add_filter( 'update_footer', array( $this, 'filter_update_footer' ) );
	}

	/**
	 * Filters the admin footer text to add our own links.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public function filter_admin_footer_text( $text ) {
		$product_link = AS3CF_Utils::dbrains_link(
			static::dbrains_url(
				'/wp-offload-media/',
				array( 'utm_campaign' => 'plugin_footer', 'utm_content' => 'footer_colophon' )
			),
			$this->plugin_name
		);

		$wpe_link = AS3CF_Utils::dbrains_link(
			static::wpe_url(
				'',
				array( 'utm_content' => static::get_wpe_url_utm_content() )
			),
			'WP Engine'
		);

		return sprintf(
		/* translators: %1$s is a link to WP Offload Media's website, and %2$s is a link to WP Engine's website. */
			__( '%1$s is developed and maintained by %2$s.', 'amazon-s3-and-cloudfront' ),
			$product_link,
			$wpe_link
		);
	}

	/**
	 * Filters the admin footer's WordPress version text to add our own links.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function filter_update_footer( $content ) {
		$links[] = AS3CF_Utils::dbrains_link(
			static::dbrains_url(
				'/wp-offload-media/docs/',
				array( 'utm_campaign' => 'plugin_footer', 'utm_content' => 'footer_navigation' )
			),
			__( 'Documentation', 'amazon-s3-and-cloudfront' )
		);

		$links[] = '<a href="' . static::get_plugin_page_url( array( 'hash' => '/support' ) ) . '">' . __( 'Support', 'amazon-s3-and-cloudfront' ) . '</a>';

		$links[] = AS3CF_Utils::dbrains_link(
			static::dbrains_url(
				'/wp-offload-media/feedback/',
				array( 'utm_campaign' => 'plugin_footer', 'utm_content' => 'footer_navigation' )
			),
			__( 'Feedback', 'amazon-s3-and-cloudfront' )
		);

		$links[] = AS3CF_Utils::dbrains_link(
			static::dbrains_url(
				'/wp-offload-media/whats-new/',
				array( 'utm_campaign' => 'plugin_footer', 'utm_content' => 'footer_navigation' )
			),
			$this->plugin_name . ' ' . $this->plugin_version,
			'whats-new'
		);

		return join( ' &#8729; ', $links );
	}
}
