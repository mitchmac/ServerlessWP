<?php

namespace DeliciousBrains\WP_Offload_Media;

interface Settings_Interface {
	/**
	 * Returns keyed array of all settings values regardless of whether explicitly set or not.
	 *
	 * @param bool $pseudo Include pseudo settings that are derived rather than saved?
	 *
	 * @return array
	 */
	public function get_all_settings( bool $pseudo = true ): array;

	/**
	 * Get a specific setting.
	 *
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function get_setting( string $key, $default = '' );

	/**
	 * Get the plugin's settings array.
	 *
	 * @param bool $force
	 *
	 * @return array
	 */
	public function get_settings( bool $force = false ): array;

	/**
	 * Gets a single setting that has been defined in the plugin settings constant.
	 *
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function get_defined_setting( string $key, $default = '' );

	/**
	 * Get all settings that have been defined via constant for the plugin.
	 *
	 * @param bool $force
	 *
	 * @return array
	 */
	public function get_defined_settings( bool $force = false ): array;

	/**
	 * Set a setting.
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function set_setting( string $key, $value );

	/**
	 * Bulk set the settings array.
	 *
	 * @param array $settings
	 */
	public function set_settings( array $settings );

	/**
	 * Save the settings to the database.
	 */
	public function save_settings();

	/**
	 * Delete a setting.
	 *
	 * @param string $key
	 */
	public function remove_setting( string $key );

	/**
	 * Ensure that sensitive settings are obfuscated.
	 *
	 * @param array $settings
	 *
	 * @return array
	 */
	public function obfuscate_sensitive_settings( array $settings ): array;

	/**
	 * Sanitize a setting value, maybe.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return array|bool|string
	 */
	public function sanitize_setting( string $key, $value );

	/**
	 * Allowed settings keys for this plugin.
	 *
	 * @param bool $include_legacy Should legacy keys be included? Optional, default false.
	 *
	 * @return array
	 */
	public function get_allowed_settings_keys( bool $include_legacy = false ): array;

	/**
	 * Get the settings that should not be shown once saved.
	 *
	 * @return array
	 */
	public function get_sensitive_settings(): array;

	/**
	 * Get the blacklisted settings for monitoring changes in defines.
	 * These settings will not be saved in the database.
	 *
	 * @return array
	 */
	public function get_monitored_settings_blacklist(): array;

	/**
	 * List of settings that should skip full sanitize.
	 *
	 * @return array
	 */
	public function get_skip_sanitize_settings(): array;

	/**
	 * List of settings that should be treated as paths.
	 *
	 * @return array
	 */
	public function get_path_format_settings(): array;

	/**
	 * List of settings that should be treated as directory paths.
	 *
	 * @return array
	 */
	public function get_prefix_format_settings(): array;

	/**
	 * List of settings that should be treated as booleans.
	 *
	 * @return array
	 */
	public function get_boolean_format_settings(): array;

	/**
	 * Getter for $saving_settings.
	 *
	 * @return bool
	 */
	public function saving_settings(): bool;

	/**
	 * Setter for $saving_settings.
	 *
	 * @param bool $saving
	 */
	public function set_saving_settings( bool $saving );
}
