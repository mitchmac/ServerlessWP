<?php

namespace DeliciousBrains\WP_Offload_Media;

use AS3CF_Utils;

trait Settings_Trait {
	/**
	 * Are only legacy defines in use?
	 *
	 * @var bool
	 */
	protected static $legacy_defines = false;

	/**
	 * @var array
	 */
	private $settings = array();

	/**
	 * @var array
	 */
	private $defined_settings = array();

	/**
	 * @var bool
	 */
	private $saving_settings = false;

	/**
	 * Returns keyed array of all settings values regardless of whether explicitly set or not.
	 *
	 * @param bool $pseudo Include pseudo settings that are derived rather than saved?
	 *
	 * @return array
	 */
	public function get_all_settings( bool $pseudo = true ): array {
		$settings = array();

		/*
		 * Settings that can be defined one way or another.
		 */

		foreach ( $this->get_allowed_settings_keys() as $key ) {
			$settings[ $key ] = $this->sanitize_setting( $key, $this->get_setting( $key ) );
		}

		return $settings;
	}

	/**
	 * Get the plugin's settings array.
	 *
	 * @param bool $force
	 *
	 * @return array
	 */
	public function get_settings( bool $force = false ): array {
		if ( empty( $this->settings ) || $force ) {
			$this->settings = array();

			$saved_settings = get_site_option( static::SETTINGS_KEY );
			$saved_settings = ! empty( $saved_settings ) && is_array( $saved_settings ) ? $saved_settings : array();

			$this->settings = $this->filter_settings( $saved_settings );

			// Now that we have merged database and defined settings, sanitize them before use.
			if ( ! empty( $this->settings ) ) {
				foreach ( $this->settings as $key => $val ) {
					$this->settings[ $key ] = $this->sanitize_setting( $key, $val );
				}
			}

			// If defined settings keys have changed since last time settings were saved to database,
			// re-save to remove the new keys.
			if (
				! empty( $saved_settings ) &&
				! empty( $this->defined_settings ) &&
				! empty( array_intersect_key( $saved_settings, $this->defined_settings ) )
			) {
				$this->save_settings();
			}
		}

		return $this->settings;
	}

	/**
	 * Gets a single setting that has been defined in the plugin settings constant.
	 *
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function get_defined_setting( string $key, $default = '' ) {
		$defined_settings = $this->get_defined_settings();

		return isset( $defined_settings[ $key ] ) ? $defined_settings[ $key ] : $default;
	}

	/**
	 * Get all settings that have been defined via constant for the plugin.
	 *
	 * @param bool $force
	 *
	 * @return array
	 */
	public function get_defined_settings( bool $force = false ): array {
		if ( empty( $this->defined_settings ) || $force ) {
			$this->defined_settings = array();

			if ( ! static::settings_constant() ) {
				$unserialized = array();
			} else {
				$unserialized = maybe_unserialize( constant( static::settings_constant() ) );
			}

			$unserialized = is_array( $unserialized ) ? $unserialized : array();

			if ( method_exists( $this, 'get_legacy_defined_settings' ) ) {
				$unserialized = $this->get_legacy_defined_settings( $unserialized );
			}

			// Nothing in new style or legacy defines, we're done.
			if ( empty( $unserialized ) ) {
				return $this->defined_settings;
			}

			foreach ( $unserialized as $key => $value ) {
				if ( ! in_array( $key, $this->get_allowed_settings_keys( true ) ) ) {
					continue;
				}

				$value = $this->sanitize_setting( $key, $value );

				$this->defined_settings[ $key ] = $value;
			}

			// Normalize the defined settings before saving, so we can detect when a real change happens.
			ksort( $this->defined_settings );

			// If only legacy defines are in use, we can fake new style to allow for key based monitoring and db settings cleanup.
			if ( ! static::settings_constant() ) {
				static::$legacy_defines = true;
				define( static::preferred_settings_constant(), serialize( $this->defined_settings ) );
			}

			$this->listen_for_settings_constant_changes();

			update_site_option( 'as3cf_constant_' . static::settings_constant(), array_diff_key( $this->defined_settings, array_flip( $this->get_monitored_settings_blacklist() ) ) );
		}

		return $this->defined_settings;
	}

	/**
	 * Are only legacy defines in use?
	 *
	 * @return bool
	 */
	public static function using_legacy_defines(): bool {
		return static::$legacy_defines;
	}

	/**
	 * Returns first (preferred) settings constant that can be defined, otherwise blank.
	 *
	 * @return string
	 */
	public static function preferred_settings_constant(): string {
		if ( ! empty( static::$settings_constants ) ) {
			return static::$settings_constants[0];
		} else {
			return '';
		}
	}

	/**
	 * Get the constant used to define the settings.
	 *
	 * @return string|false Constant name if defined, otherwise false
	 */
	public static function settings_constant() {
		return AS3CF_Utils::get_first_defined_constant( static::$settings_constants );
	}

	/**
	 * Subscribe to a change of the site option used to store the constant-defined settings.
	 */
	protected function listen_for_settings_constant_changes() {
		if (
			false !== static::settings_constant() &&
			! has_action(
				'update_site_option_as3cf_constant_' . static::settings_constant(),
				array(
					$this,
					'settings_constant_changed',
				)
			)
		) {
			add_action( 'add_site_option_as3cf_constant_' . static::settings_constant(), array(
				$this,
				'settings_constant_added',
			), 10, 3 );
			add_action( 'update_site_option_as3cf_constant_' . static::settings_constant(), array(
				$this,
				'settings_constant_changed',
			), 10, 4 );
		}
	}

	/**
	 * Translate a settings constant option addition into a change.
	 *
	 * @param string $option     Name of the option.
	 * @param mixed  $value      Value the option is being initialized with.
	 * @param int    $network_id ID of the network.
	 */
	public function settings_constant_added( string $option, $value, int $network_id ) {
		$db_settings = get_site_option( static::SETTINGS_KEY, array() );
		$this->settings_constant_changed( $option, $value, $db_settings, $network_id );
	}

	/**
	 * Callback for announcing when settings-defined values change.
	 *
	 * @param string $option       Name of the option.
	 * @param mixed  $new_settings Current value of the option.
	 * @param mixed  $old_settings Old value of the option.
	 * @param int    $network_id   ID of the network.
	 */
	public function settings_constant_changed( string $option, $new_settings, $old_settings, int $network_id ) {
		if ( ! static::settings_constant() ) {
			return;
		}

		$old_settings = $old_settings ? $old_settings : array();

		foreach ( $this->get_allowed_settings_keys( true ) as $setting ) {
			$old_value = isset( $old_settings[ $setting ] ) ? $old_settings[ $setting ] : null;
			$new_value = isset( $new_settings[ $setting ] ) ? $new_settings[ $setting ] : null;

			if ( $old_value !== $new_value ) {
				/**
				 * Setting-specific hook for setting change.
				 *
				 * @param mixed  $new_value
				 * @param mixed  $old_value
				 * @param string $setting
				 */
				do_action( 'as3cf_constant_' . static::settings_constant() . '_changed_' . $setting, $new_value, $old_value, $setting );

				/**
				 * Generic hook for setting change.
				 *
				 * @param mixed  $new_value
				 * @param mixed  $old_value
				 * @param string $setting
				 */
				do_action( 'as3cf_constant_' . static::settings_constant() . '_changed', $new_value, $old_value, $setting );
			}
		}
	}

	/**
	 * Filter in defined settings with sensible defaults.
	 *
	 * @param array $settings
	 *
	 * @return array $settings
	 */
	public function filter_settings( array $settings ): array {
		$defined_settings = $this->get_defined_settings();

		// Bail early if there are no defined settings.
		if ( empty( $defined_settings ) ) {
			return $settings;
		}

		foreach ( $defined_settings as $key => $value ) {
			$allowed_values = array();

			if ( in_array( $key, $this->get_boolean_format_settings() ) ) {
				$allowed_values = array( true, false, '0', '1' );
			}

			// Unexpected value, remove from defined_settings array.
			if ( ! empty( $allowed_values ) && ! in_array( $value, $allowed_values ) ) {
				$this->remove_defined_setting( $key );
				continue;
			}

			// Value defined successfully.
			$settings[ $key ] = $value;
		}

		return $settings;
	}

	/**
	 * Ensure that sensitive settings are obfuscated.
	 *
	 * @param array $settings
	 *
	 * @return array
	 */
	public function obfuscate_sensitive_settings( array $settings ): array {
		$sensitive_settings = $this->get_sensitive_settings();

		if ( empty( $sensitive_settings ) ) {
			return $settings;
		}

		foreach ( $settings as $key => $value ) {
			if ( ! empty( $value ) && in_array( $key, $sensitive_settings ) ) {
				$settings[ $key ] = _x( '-- not shown --', 'placeholder for sensitive setting, e.g. secret access key', 'amazon-s3-and-cloudfront' );
			}
		}

		return $settings;
	}

	/**
	 * Sanitize a setting value, maybe.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return array|bool|string
	 */
	public function sanitize_setting( string $key, $value ) {
		$skip_sanitize    = $this->get_skip_sanitize_settings();
		$boolean_settings = $this->get_boolean_format_settings();

		if ( in_array( $key, $skip_sanitize ) ) {
			if ( is_array( $value ) ) {
				$result = array();
				foreach ( $value as $k => $v ) {
					$result[ $k ] = wp_strip_all_tags( $v );
				}
				$value = $result;
			} else {
				$value = wp_strip_all_tags( $value );
			}
		} elseif ( in_array( $key, $boolean_settings ) ) {
			$value = (bool) $value;
		} elseif ( is_numeric( $value ) ) {
			$value = strval( $value );
		} else {
			$value = sanitize_text_field( $value );

			// Make sure path setting is absolute and not just "/".
			// But not on Windows as it can have various forms of path, e.g. C:\Sites and \\shared\sites.
			if ( '/' === DIRECTORY_SEPARATOR && in_array( $key, $this->get_path_format_settings() ) ) {
				$value = trim( AS3CF_Utils::unleadingslashit( $value ) );
				$value = empty( $value ) ? '' : AS3CF_Utils::leadingslashit( $value );
			}

			// Make sure prefix setting is relative with trailing slash for visibility.
			if ( in_array( $key, $this->get_prefix_format_settings() ) ) {
				$value = trim( untrailingslashit( $value ) );
				$value = empty( $value ) ? '' : AS3CF_Utils::trailingslash_prefix( $value );
			}
		}

		return $value;
	}

	/**
	 * Set a setting.
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function set_setting( string $key, $value ) {
		$this->get_settings();

		$this->settings[ $key ] = $value;
	}

	/**
	 * Bulk set the settings array.
	 *
	 * @param array $settings
	 */
	public function set_settings( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Save the settings to the database.
	 */
	public function save_settings() {
		if ( is_array( $this->settings ) ) {
			ksort( $this->settings );
		}

		$this->update_site_option( static::SETTINGS_KEY, array_diff_key( $this->settings, $this->defined_settings ) );
	}

	/**
	 * Update site option.
	 *
	 * @param string $option
	 * @param mixed  $value
	 * @param bool   $autoload
	 *
	 * @return bool
	 */
	public function update_site_option( string $option, $value, bool $autoload = true ): bool {
		if ( is_multisite() ) {
			return update_site_option( $option, $value );
		}

		return update_option( $option, $value, $autoload );
	}

	/**
	 * Delete a setting.
	 *
	 * @param string $key
	 */
	public function remove_setting( string $key ) {
		$this->get_settings();
		unset( $this->settings[ $key ] );
	}

	/**
	 * Removes a defined setting from the defined_settings array.
	 *
	 * Does not unset the actual constant.
	 *
	 * @param string $key
	 */
	public function remove_defined_setting( string $key ) {
		$this->get_defined_settings();
		unset( $this->defined_settings[ $key ] );
	}

	/**
	 * Helper for displaying boolean settings.
	 *
	 * @param string $key setting key
	 *
	 * @return string
	 */
	public function on_off( string $key ): string {
		$value = $this->get_setting( $key, false );

		return true === $value ? 'On' : 'Off';
	}

	/**
	 * Getter for $saving_settings.
	 *
	 * @return bool
	 */
	public function saving_settings(): bool {
		return $this->saving_settings;
	}

	/**
	 * Setter for $saving_settings.
	 *
	 * @param bool $saving
	 */
	public function set_saving_settings( bool $saving ) {
		$this->saving_settings = $saving;
	}
}
