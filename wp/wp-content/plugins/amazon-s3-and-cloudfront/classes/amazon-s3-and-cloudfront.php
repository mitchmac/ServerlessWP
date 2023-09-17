<?php

use DeliciousBrains\WP_Offload_Media\API\API_Manager;
use DeliciousBrains\WP_Offload_Media\API\V1\Buckets;
use DeliciousBrains\WP_Offload_Media\API\V1\Diagnostics;
use DeliciousBrains\WP_Offload_Media\API\V1\Notifications;
use DeliciousBrains\WP_Offload_Media\API\V1\Settings;
use DeliciousBrains\WP_Offload_Media\API\V1\State;
use DeliciousBrains\WP_Offload_Media\API\V1\URL_Preview;
use DeliciousBrains\WP_Offload_Media\Integrations\Core as Core_Integration;
use DeliciousBrains\WP_Offload_Media\Integrations\Integration_Manager;
use DeliciousBrains\WP_Offload_Media\Integrations\Media_Library as Media_Library_Integration;
use DeliciousBrains\WP_Offload_Media\Integrations\Advanced_Custom_Fields as Advanced_Custom_Fields_Integration;
use DeliciousBrains\WP_Offload_Media\Items\Download_Handler;
use DeliciousBrains\WP_Offload_Media\Items\Item;
use DeliciousBrains\WP_Offload_Media\Items\Item_Handler;
use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;
use DeliciousBrains\WP_Offload_Media\Items\Remove_Local_Handler;
use DeliciousBrains\WP_Offload_Media\Items\Remove_Provider_Handler;
use DeliciousBrains\WP_Offload_Media\Items\Upload_Handler;
use DeliciousBrains\WP_Offload_Media\Providers\Delivery\AWS_CloudFront;
use DeliciousBrains\WP_Offload_Media\Providers\Delivery\Cloudflare;
use DeliciousBrains\WP_Offload_Media\Providers\Delivery\Delivery_Provider;
use DeliciousBrains\WP_Offload_Media\Providers\Delivery\DigitalOcean_Spaces_CDN;
use DeliciousBrains\WP_Offload_Media\Providers\Delivery\GCP_CDN;
use DeliciousBrains\WP_Offload_Media\Providers\Delivery\KeyCDN;
use DeliciousBrains\WP_Offload_Media\Providers\Delivery\Other;
use DeliciousBrains\WP_Offload_Media\Providers\Delivery\StackPath;
use DeliciousBrains\WP_Offload_Media\Providers\Delivery\Storage;
use DeliciousBrains\WP_Offload_Media\Providers\Provider;
use DeliciousBrains\WP_Offload_Media\Providers\Storage\AWS_Provider;
use DeliciousBrains\WP_Offload_Media\Providers\Storage\DigitalOcean_Provider;
use DeliciousBrains\WP_Offload_Media\Providers\Storage\GCP_Provider;
use DeliciousBrains\WP_Offload_Media\Providers\Storage\Null_Provider;
use DeliciousBrains\WP_Offload_Media\Providers\Storage\Storage_Provider;
use DeliciousBrains\WP_Offload_Media\Settings\Validation_Manager;
use DeliciousBrains\WP_Offload_Media\Upgrades\Upgrade;
use DeliciousBrains\WP_Offload_Media\Upgrades\Upgrade_Clear_Postmeta_Cache;
use DeliciousBrains\WP_Offload_Media\Upgrades\Upgrade_Content_Replace_URLs;
use DeliciousBrains\WP_Offload_Media\Upgrades\Upgrade_EDD_Replace_URLs;
use DeliciousBrains\WP_Offload_Media\Upgrades\Upgrade_File_Sizes;
use DeliciousBrains\WP_Offload_Media\Upgrades\Upgrade_Filter_Post_Excerpt;
use DeliciousBrains\WP_Offload_Media\Upgrades\Upgrade_Fix_Broken_Item_Extra_Data;
use DeliciousBrains\WP_Offload_Media\Upgrades\Upgrade_Item_Extra_Data;
use DeliciousBrains\WP_Offload_Media\Upgrades\Upgrade_Items_Table;
use DeliciousBrains\WP_Offload_Media\Upgrades\Upgrade_Meta_WP_Error;
use DeliciousBrains\WP_Offload_Media\Upgrades\Upgrade_Region_Meta;
use DeliciousBrains\WP_Offload_Media\Upgrades\Upgrade_Tools_Errors;
use DeliciousBrains\WP_Offload_Media\Upgrades\Upgrade_WPOS3_To_AS3CF;

class Amazon_S3_And_CloudFront extends AS3CF_Plugin_Base {

	/**
	 * @var Storage_Provider
	 */
	private $storage_provider;

	/**
	 * @var array
	 */
	private $storage_providers = array();

	/**
	 * @var Storage_Provider
	 */
	private $provider_client;

	/**
	 * @var string
	 */
	private $provider_client_region;

	/**
	 * @var Delivery_Provider
	 */
	private $delivery_provider;

	/**
	 * @var array
	 */
	private $uploaded_post_ids = array();

	/**
	 * @var string
	 */
	protected $plugin_title;

	/**
	 * @var string
	 */
	protected $plugin_menu_title;

	/**
	 * @var string
	 */
	protected $plugin_prefix = 'as3cf';

	/**
	 * @var AS3CF_Local_To_S3
	 */
	public $filter_local;

	/**
	 * @var AS3CF_S3_To_Local
	 */
	public $filter_provider;

	/**
	 * @var AS3CF_Notices
	 */
	public $notices;

	/**
	 * @var Validation_Manager
	 */
	public $validation_manager;

	/**
	 * @var string
	 */
	public $hook_suffix;

	/**
	 * @var array Store if each bucket, used by the plugin and addons, is writable
	 */
	protected static $buckets_check = array();

	/**
	 * @var string
	 */
	protected static $default_storage_provider = 'aws';

	/**
	 * @var string
	 */
	protected static $default_delivery_provider = 'storage';

	/**
	 * @var array Known storage provider classes.
	 */
	protected static $storage_provider_classes = array();

	/**
	 * @var array Known delivery provider classes.
	 */
	protected static $delivery_provider_classes = array();

	/**
	 * @var Item_Handler[]
	 */
	protected $item_handlers = array();

	/**
	 * @var AS3CF_Plugin_Compatibility
	 */
	public $plugin_compat;

	const DEFAULT_EXPIRES = 900;

	const SETTINGS_KEY = 'tantan_wordpress_s3';

	/**
	 * @var array
	 */
	protected static $settings_constants = array(
		'AS3CF_SETTINGS',
		'WPOS3_SETTINGS',
	);

	/**
	 * Class map to determine Item subclass per item source type
	 *
	 * @var string[]
	 */
	private $source_type_classes = array(
		'media-library' => 'DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item',
	);

	/**
	 * Class map to determine Item subclass per item summary type
	 *
	 * @var string[]
	 */
	private $summary_type_classes = array(
		'media-library' => 'DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item',
	);

	/**
	 * @var Integration_Manager
	 */
	protected $integration_manager;

	/**
	 * @var API_Manager
	 */
	protected $api_manager;

	const LATEST_UPGRADE_ROUTINE = 12;

	/**
	 * @param string      $plugin_file_path
	 * @param string|null $slug
	 *
	 * @throws Exception
	 */
	public function __construct( $plugin_file_path, $slug = null ) {
		$this->plugin_slug = ( is_null( $slug ) ) ? 'amazon-s3-and-cloudfront' : $slug;

		parent::__construct( $plugin_file_path );

		$this->notices            = AS3CF_Notices::get_instance( $this );
		$this->validation_manager = new Validation_Manager( $this );

		$this->init( $plugin_file_path );
	}

	/**
	 * Abstract class constructor
	 *
	 * @param string $plugin_file_path
	 */
	public function init( $plugin_file_path ) {
		$this->plugin_title      = __( 'WP Offload Media Lite', 'amazon-s3-and-cloudfront' );
		$this->plugin_menu_title = __( 'WP Offload Media', 'amazon-s3-and-cloudfront' );

		static::$storage_provider_classes = apply_filters( 'as3cf_storage_provider_classes', array(
			AWS_Provider::get_provider_key_name()          => 'DeliciousBrains\WP_Offload_Media\Providers\Storage\AWS_Provider',
			DigitalOcean_Provider::get_provider_key_name() => 'DeliciousBrains\WP_Offload_Media\Providers\Storage\DigitalOcean_Provider',
			GCP_Provider::get_provider_key_name()          => 'DeliciousBrains\WP_Offload_Media\Providers\Storage\GCP_Provider',
		) );

		static::$delivery_provider_classes = apply_filters( 'as3cf_delivery_provider_classes', array(
			// First Party CDNs.
			AWS_CloudFront::get_provider_key_name()          => 'DeliciousBrains\WP_Offload_Media\Providers\Delivery\AWS_CloudFront',
			DigitalOcean_Spaces_CDN::get_provider_key_name() => 'DeliciousBrains\WP_Offload_Media\Providers\Delivery\DigitalOcean_Spaces_CDN',
			GCP_CDN::get_provider_key_name()                 => 'DeliciousBrains\WP_Offload_Media\Providers\Delivery\GCP_CDN',
			// Third Party CDNs.
			Cloudflare::get_provider_key_name()              => 'DeliciousBrains\WP_Offload_Media\Providers\Delivery\Cloudflare',
			KeyCDN::get_provider_key_name()                  => 'DeliciousBrains\WP_Offload_Media\Providers\Delivery\KeyCDN',
			StackPath::get_provider_key_name()               => 'DeliciousBrains\WP_Offload_Media\Providers\Delivery\StackPath',
			// Fallback to raw storage URLs.
			Storage::get_provider_key_name()                 => 'DeliciousBrains\WP_Offload_Media\Providers\Delivery\Storage',
			// User defined Third Party.
			Other::get_provider_key_name()                   => 'DeliciousBrains\WP_Offload_Media\Providers\Delivery\Other',
		) );

		// Listen for settings changes.
		if ( false !== static::settings_constant() ) {
			add_action( 'as3cf_constant_' . static::settings_constant() . '_changed_bucket', array( $this, 'process_bucket_change_after_init' ) );
		}

		$this->set_storage_provider();
		$this->set_delivery_provider();

		// Bundled SDK may require AWS setup before any use.
		$this->handle_aws_access_key_migration();

		// Only instantiate upgrade classes on single site installs or primary subsite.
		if ( ! is_multisite() || is_network_admin() || $this->is_current_blog( get_current_blog_id() ) ) {
			new Upgrade_Region_Meta( $this );
			new Upgrade_File_Sizes( $this );
			new Upgrade_Meta_WP_Error( $this );
			new Upgrade_Content_Replace_URLs( $this );
			new Upgrade_EDD_Replace_URLs( $this );
			new Upgrade_Filter_Post_Excerpt( $this );
			new Upgrade_WPOS3_To_AS3CF( $this );
			new Upgrade_Items_Table( $this );
			new Upgrade_Tools_Errors( $this );
			new Upgrade_Item_Extra_Data( $this );
			new Upgrade_Clear_Postmeta_Cache( $this );
			new Upgrade_Fix_Broken_Item_Extra_Data( $this );
		}

		// Plugin setup
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'aws_admin_menu', array( $this, 'aws_admin_menu' ) );
		add_filter( 'plugin_action_links', array( $this, 'plugin_actions_settings_link' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( $this, 'plugin_actions_settings_link' ), 10, 2 );
		add_filter( 'pre_get_space_used', array( $this, 'multisite_get_space_used' ) );

		// display a notice when either lite or pro is automatically deactivated
		add_action( 'pre_current_active_plugins', array( $this, 'plugin_deactivated_notice' ) );
		add_action( 'as3cf_plugin_load', array( $this, 'remove_access_keys_if_constants_set' ) );

		// Enable integrations once everything has been initialized.
		add_action( 'as3cf_init', array( $this, 'enable_integrations' ) );

		// Register REST API Endpoints once everything has been initialized.
		add_action( 'as3cf_init', array( $this, 'register_api_endpoints' ) );

		// Keep track of whether the settings we're responsible for are currently being saved.
		add_action( 'as3cf_pre_save_settings', function () {
			$this->set_saving_settings( true );
		} );
		add_action( 'as3cf_post_save_settings', function () {
			$this->set_saving_settings( false );
		} );

		// Content filtering
		$this->filter_local    = new AS3CF_Local_To_S3( $this );
		$this->filter_provider = new AS3CF_S3_To_Local( $this );

		load_plugin_textdomain( 'amazon-s3-and-cloudfront', false, dirname( plugin_basename( $plugin_file_path ) ) . '/languages/' );

		// Register modal scripts and styles
		$this->register_modal_assets();
	}

	/**
	 * Register all REST API endpoints this plugin handles.
	 *
	 * @param Amazon_S3_And_CloudFront $as3cf
	 */
	public function register_api_endpoints( Amazon_S3_And_CloudFront $as3cf ) {
		$this->api_manager = API_Manager::get_instance();

		$api_endpoints = array();

		if ( is_admin() || AS3CF_Utils::is_rest_api() ) {
			$api_endpoints = array(
				Buckets::name()       => new Buckets( $as3cf ),
				Diagnostics::name()   => new Diagnostics( $as3cf ),
				Notifications::name() => new Notifications( $as3cf ),
				Settings::name()      => new Settings( $as3cf ),
				State::name()         => new State( $as3cf ),
				URL_Preview::name()   => new URL_Preview( $as3cf ),
			);
		}

		/**
		 * Filters which API endpoint handlers to enable. To disable an endpoint
		 * implement this filter and unset all unwanted endpoints from the array.
		 *
		 * @param array $api_endpoints Associative array of API endpoint handlers
		 */
		$api_endpoints = apply_filters( 'as3cf_api_endpoints', $api_endpoints );

		foreach ( $api_endpoints as $name => $api ) {
			$this->api_manager->register_api_endpoint( $name, $api );
		}
	}

	/**
	 * Enable integrations.
	 *
	 * @param Amazon_S3_And_CloudFront $as3cf
	 */
	public function enable_integrations( Amazon_S3_And_CloudFront $as3cf ) {
		// Include standard compatibility code for other plugins.
		$this->plugin_compat = new AS3CF_Plugin_Compatibility( $this );

		$this->integration_manager = Integration_Manager::get_instance();

		/**
		 * Filters which integrations to enable. To disable an integration
		 * implement this filter and unset all unwanted integrations from
		 * the array.
		 *
		 * @param array $integrations Associative array of integrations
		 */
		$integrations = apply_filters( 'as3cf_integrations', array(
			'core' => new Core_Integration( $as3cf ),
			'mlib' => new Media_Library_Integration( $as3cf ),
			'acf'  => new Advanced_Custom_Fields_Integration( $as3cf ),
		) );

		foreach ( $integrations as $integration_key => $integration ) {
			$this->integration_manager->register_integration( $integration_key, $integration );
		}
	}

	/**
	 * Get the currently configured Storage Provider.
	 *
	 * @return Storage_Provider|null
	 */
	public function get_storage_provider() {
		return $this->storage_provider;
	}

	/**
	 * Backwards compat alias function for `get_storage_provider()`.
	 *
	 * @return Provider
	 *
	 * @deprecated Use get_storage_provider
	 */
	public function get_provider() {
		return $this->get_storage_provider();
	}

	/**
	 * Get an instance of a storage provider object.
	 *
	 * If $storage_provider_key equals the configured storage provider string,
	 * the default/configured storage provider instance is returned.
	 *
	 * @param string $storage_provider_key
	 *
	 * @return Storage_Provider|null
	 */
	public function get_storage_provider_instance( string $storage_provider_key ): ?Storage_Provider {
		if ( isset( $this->storage_providers[ $storage_provider_key ] ) ) {
			return $this->storage_providers[ $storage_provider_key ];
		}

		$default_storage_provider_key = $this->get_core_setting( 'provider' );
		if ( $storage_provider_key === $default_storage_provider_key ) {
			$storage_provider = $this->get_storage_provider();

			// Avoid caching if it's not initialized.
			if ( ! is_null( $storage_provider ) ) {
				$this->storage_providers[ $storage_provider_key ] = $storage_provider;
			}

			return $storage_provider;
		}

		// The key is not cached already and isn't the key for the default provider Attempt to create new instance.
		if ( ! empty( self::$storage_provider_classes[ $storage_provider_key ] ) ) {
			$this->storage_providers[ $storage_provider_key ] = new self::$storage_provider_classes[ $storage_provider_key ]( $this );

			return $this->storage_providers[ $storage_provider_key ];
		}

		return null;
	}

	/**
	 * Set the storage provider to be used by class.
	 *
	 * @param Storage_Provider|string|null $storage_provider
	 */
	public function set_storage_provider( $storage_provider = null ) {
		if ( empty( $storage_provider ) ) {
			$storage_provider = $this->get_core_setting( 'provider' );
		}

		// Specified provider does not exist, fall back to default.
		if (
			is_string( $storage_provider ) &&
			( empty( $storage_provider ) || empty( self::$storage_provider_classes[ $storage_provider ] ) )
		) {
			$storage_provider = static::get_default_storage_provider();

			// As long as problem does not come from defined value, set default in settings, but don't save permanently yet.
			if ( false === $this->get_defined_setting( 'provider', false ) ) {
				$this->set_setting( 'provider', $storage_provider );
			}
		}

		if ( is_string( $storage_provider ) && ! empty( self::$storage_provider_classes[ $storage_provider ] ) ) {
			$storage_provider = new self::$storage_provider_classes[ $storage_provider ]( $this );
		}

		if ( ! empty( $storage_provider ) && ! is_string( $storage_provider ) ) {
			$this->storage_provider = $storage_provider;
		} else {
			// We really tried, we really did, but we're going to have to let things fail.
			$this->storage_provider = null;
		}

		if ( ! empty( $this->storage_provider ) ) {
			$this->validation_manager->register_validator( $this->storage_provider::VALIDATOR_KEY, $this->storage_provider );
		}
	}

	/**
	 * Get the currently configured Delivery Provider.
	 *
	 * @return Delivery_Provider|null
	 */
	public function get_delivery_provider() {
		return $this->delivery_provider;
	}

	/**
	 * Set the delivery provider to be used by class.
	 *
	 * @param Delivery_Provider|string|null $delivery_provider
	 */
	public function set_delivery_provider( $delivery_provider = null ) {
		if ( empty( $delivery_provider ) ) {
			$delivery_provider = $this->get_core_setting( 'delivery-provider' );
		}

		// Specified provider does not exist, fall back to default.
		if (
			is_string( $delivery_provider ) &&
			( empty( $delivery_provider ) || empty( self::$delivery_provider_classes[ $delivery_provider ] ) )
		) {
			$delivery_provider = static::get_default_delivery_provider();

			// As long as problem does not come from defined value, set default in settings, but don't save permanently yet.
			if ( false === $this->get_defined_setting( 'delivery-provider', false ) ) {
				$this->set_setting( 'delivery-provider', $delivery_provider );
			}
		}

		if ( is_string( $delivery_provider ) && ! empty( self::$delivery_provider_classes[ $delivery_provider ] ) ) {
			$delivery_provider = new self::$delivery_provider_classes[ $delivery_provider ]( $this );
		}

		if ( ! empty( $delivery_provider ) && ! is_string( $delivery_provider ) ) {
			$this->delivery_provider = $delivery_provider;
		} else {
			// We really tried, we really did, but we're going to have to let things fail.
			$this->delivery_provider = null;
		}

		if ( ! empty( $this->delivery_provider ) ) {
			$this->validation_manager->register_validator( $this->delivery_provider::VALIDATOR_KEY, $this->delivery_provider );
		}
	}

	/**
	 * Returns the currently supported Providers.
	 *
	 * @param string $type Which type of provider, "storage" or "delivery".
	 *
	 * @return array
	 */
	public function get_provider_classes( $type ) {
		switch ( $type ) {
			case 'storage':
				$providers = self::$storage_provider_classes;
				break;
			case 'delivery':
				$providers = self::$delivery_provider_classes;
				break;
			default:
				$providers = array(); // Error.
		}

		return $providers;
	}

	/**
	 * Returns provider class name for given key.
	 *
	 * @param string $key_name
	 * @param string $type
	 *
	 * @return Provider|null
	 */
	public function get_provider_class( $key_name, $type = 'storage' ) {
		if ( empty( $type ) ) {
			$type = 'storage';
		}
		$classes = $this->get_provider_classes( $type );

		return empty( $classes[ $key_name ] ) ? null : $classes[ $key_name ];
	}

	/**
	 * Provider name for given key.
	 *
	 * @param string $key_name
	 * @param string $type
	 *
	 * @return string
	 */
	public function get_provider_name( $key_name, $type = 'storage' ) {
		if ( empty( $type ) ) {
			$type = 'storage';
		}
		$class = $this->get_provider_class( $key_name, $type );

		return empty( $class ) ? __( 'Unknown', 'amazon-s3-and-cloudfront' ) : $class::get_provider_name();
	}

	/**
	 * Provider & Service name for given key.
	 *
	 * @param string $key_name
	 * @param string $type
	 *
	 * @return string
	 */
	public function get_provider_service_name( $key_name, $type = 'storage' ) {
		if ( empty( $type ) ) {
			$type = 'storage';
		}
		$class = $this->get_provider_class( $key_name, $type );

		return empty( $class ) ? __( 'Unknown', 'amazon-s3-and-cloudfront' ) : $class::get_provider_service_name();
	}

	/**
	 * Get an array of useful storage provider details.
	 *
	 * @return array
	 */
	public function get_available_storage_provider_details() {
		$providers = array();

		$offloaded = $this->media_counts()['offloaded'];

		/**
		 * @var string           $provider_key
		 * @var Storage_Provider $provider_class
		 */
		foreach ( $this->get_provider_classes( 'storage' ) as $provider_key => $provider_class ) {
			$providers[ $provider_key ] = array(
				'provider_key_name'                                  => $provider_class::get_provider_key_name(),
				'provider_name'                                      => $provider_class::get_provider_name(),
				'service_name'                                       => $provider_class::get_service_name(),
				'service_key_name'                                   => $provider_class::get_service_key_name(),
				'default_provider_service_name'                      => $provider_class::get_provider_service_name( false ),
				'provider_service_name'                              => $provider_class::get_provider_service_name(),
				'icon_desc'                                          => $provider_class::get_icon_desc(),
				'provider_service_quick_start_url'                   => $provider_class::get_provider_service_quick_start_url(),
				'provider_service_name_override_allowed'             => $provider_class::provider_service_name_override_allowed(),
				'use_access_keys_allowed'                            => $provider_class::use_access_keys_allowed(),
				'use_server_roles_allowed'                           => $provider_class::use_server_roles_allowed(),
				'use_key_file_allowed'                               => $provider_class::use_key_file_allowed(),
				'regions'                                            => $provider_class::get_regions(),
				'default_region'                                     => $provider_class::get_default_region(),
				'region_required'                                    => $provider_class::region_required(),
				'media_already_offloaded_warning'                    => $provider_class::get_media_already_offloaded_warning( $offloaded ),
				'use_server_roles_title'                             => $provider_class::get_use_server_roles_title(),
				'define_access_keys_desc'                            => $provider_class::get_define_access_keys_desc(),
				'define_access_keys_example'                         => $provider_class::get_define_access_keys_example(),
				'enter_access_keys_desc'                             => $provider_class::get_enter_access_keys_desc(),
				'get_access_keys_help'                               => $provider_class::get_access_keys_help(),
				'define_key_file_desc'                               => $provider_class::get_define_key_file_desc(),
				'define_key_file_example'                            => $provider_class::get_define_key_file_example(),
				'enter_key_file_desc'                                => $provider_class::get_enter_key_file_desc(),
				'use_server_roles_desc'                              => $provider_class::get_use_server_roles_desc(),
				'use_server_roles_example'                           => $provider_class::get_use_server_roles_example(),
				'defined_auth_desc'                                  => $provider_class::get_defined_auth_desc(),
				'used_access_keys_constants'                         => $provider_class::used_access_keys_constants(),
				'used_key_file_path_constants'                       => $provider_class::used_key_file_path_constants(),
				'used_server_roles_constants'                        => $provider_class::used_server_roles_constants(),
				'block_public_access_warning'                        => $provider_class::get_block_public_access_warning(),
				'block_public_access_supported'                      => $provider_class::block_public_access_supported(),
				'block_public_access_enabled_supported_desc'         => $provider_class::get_block_public_access_enabled_supported_desc(),
				'block_public_access_enabled_unsupported_desc'       => $provider_class::get_block_public_access_enabled_unsupported_desc(),
				'block_public_access_enabled_unsupported_setup_desc' => $provider_class::get_block_public_access_enabled_unsupported_setup_desc(),
				'block_public_access_disabled_supported_desc'        => $provider_class::get_block_public_access_disabled_supported_desc(),
				'block_public_access_disabled_unsupported_desc'      => $provider_class::get_block_public_access_disabled_unsupported_desc(),
				'object_ownership_enforced_warning'                  => $provider_class::get_object_ownership_enforced_warning(),
				'object_ownership_supported'                         => $provider_class::object_ownership_supported(),
				'object_ownership_enforced_supported_desc'           => $provider_class::get_object_ownership_enforced_supported_desc(),
				'object_ownership_enforced_unsupported_desc'         => $provider_class::get_object_ownership_enforced_unsupported_desc(),
				'object_ownership_enforced_unsupported_setup_desc'   => $provider_class::get_object_ownership_enforced_unsupported_setup_desc(),
				'object_ownership_not_enforced_supported_desc'       => $provider_class::get_object_ownership_not_enforced_supported_desc(),
				'object_ownership_not_enforced_unsupported_desc'     => $provider_class::get_object_ownership_not_enforced_unsupported_desc(),
				'requires_acls'                                      => $provider_class::requires_acls(),
				'console_title'                                      => $provider_class::get_console_title(),
			);
		}

		return $providers;
	}

	/**
	 * Get an array of useful delivery provider details.
	 *
	 * @param string $storage_provider_key Optionally filter the result by supported storage providers
	 *
	 * @return array
	 */
	public function get_available_delivery_provider_details( string $storage_provider_key = '' ): array {
		$providers = array();

		/**
		 * @var string            $provider_key
		 * @var Delivery_Provider $provider_class
		 */
		foreach ( $this->get_provider_classes( 'delivery' ) as $provider_key => $provider_class ) {
			if ( ! empty( $storage_provider_key ) && ! $provider_class::supports_storage( $storage_provider_key ) ) {
				continue;
			}

			$providers[ $provider_key ] = array(
				'provider_key_name'                                  => $provider_class::get_provider_key_name(),
				'provider_name'                                      => $provider_class::get_provider_name(),
				'service_name'                                       => $provider_class::get_service_name(),
				'service_key_name'                                   => $provider_class::get_service_key_name(),
				'default_provider_service_name'                      => $provider_class::get_provider_service_name( false ),
				'provider_service_name'                              => $provider_class::get_provider_service_name(),
				'icon_desc'                                          => $provider_class::get_icon_desc(),
				'provider_service_quick_start_url'                   => $provider_class::get_provider_service_quick_start_url(),
				'provider_service_name_override_allowed'             => $provider_class::provider_service_name_override_allowed(),
				'supported_storage_providers'                        => $provider_class::get_supported_storage_providers(),
				'rewrite_media_urls_desc'                            => $provider_class::get_rewrite_media_urls_desc(),
				'delivery_domain_allowed'                            => $provider_class::delivery_domain_allowed(),
				'delivery_domain_desc'                               => $provider_class::get_delivery_domain_desc(),
				'use_signed_urls_key_file_allowed'                   => $provider_class::use_signed_urls_key_file_allowed(),
				'signed_urls_option_name'                            => $provider_class::signed_urls_option_name(),
				'signed_urls_option_description'                     => $provider_class::signed_urls_option_description(),
				'signed_urls_key_id_name'                            => $provider_class::signed_urls_key_id_name(),
				'signed_urls_key_id_description'                     => $provider_class::signed_urls_key_id_description(),
				'signed_urls_key_file_path_name'                     => $provider_class::signed_urls_key_file_path_name(),
				'signed_urls_key_file_path_description'              => $provider_class::signed_urls_key_file_path_description(),
				'signed_urls_object_prefix_name'                     => $provider_class::signed_urls_object_prefix_name(),
				'signed_urls_object_prefix_description'              => $provider_class::signed_urls_object_prefix_description(),
				'signed_urls_key_file_path_placeholder'              => $provider_class::signed_urls_key_file_path_placeholder(),
				'edge_server_support_desc'                           => $provider_class::edge_server_support_desc(),
				'signed_urls_support_desc'                           => $provider_class::signed_urls_support_desc(),
				'block_public_access_supported'                      => $provider_class::block_public_access_supported(),
				'block_public_access_enabled_supported_desc'         => $provider_class::get_block_public_access_enabled_supported_desc(),
				'block_public_access_enabled_unsupported_desc'       => $provider_class::get_block_public_access_enabled_unsupported_desc(),
				'block_public_access_enabled_unsupported_setup_desc' => $provider_class::get_block_public_access_enabled_unsupported_setup_desc(),
				'block_public_access_disabled_supported_desc'        => $provider_class::get_block_public_access_disabled_supported_desc(),
				'block_public_access_disabled_unsupported_desc'      => $provider_class::get_block_public_access_disabled_unsupported_desc(),
				'block_public_access_confirm_setup_prompt'           => $provider_class::get_block_public_access_confirm_setup_prompt(),
				'object_ownership_supported'                         => $provider_class::object_ownership_supported(),
				'object_ownership_enforced_supported_desc'           => $provider_class::get_object_ownership_enforced_supported_desc(),
				'object_ownership_enforced_unsupported_desc'         => $provider_class::get_object_ownership_enforced_unsupported_desc(),
				'object_ownership_enforced_unsupported_setup_desc'   => $provider_class::get_object_ownership_enforced_unsupported_setup_desc(),
				'object_ownership_not_enforced_supported_desc'       => $provider_class::get_object_ownership_not_enforced_supported_desc(),
				'object_ownership_not_enforced_unsupported_desc'     => $provider_class::get_object_ownership_not_enforced_unsupported_desc(),
				'object_ownership_confirm_setup_prompt'              => $provider_class::get_object_ownership_confirm_setup_prompt(),
				'requires_acls'                                      => $provider_class::requires_acls(),
				'console_title'                                      => $provider_class::get_console_title(),
			);
		}

		return $providers;
	}

	/**
	 * Getter for the Integrations Manager instance
	 *
	 * @return Integration_Manager
	 */
	public function get_integration_manager() {
		return $this->integration_manager;
	}

	/**
	 * Getter for the API Manager instance.
	 *
	 * @return API_Manager
	 */
	public function get_api_manager(): API_Manager {
		return $this->api_manager;
	}

	/**
	 * Get the plugin title to be used in page headings
	 *
	 * @return string
	 */
	function get_plugin_page_title() {
		return apply_filters( 'as3cf_settings_page_title', $this->plugin_title );
	}

	/**
	 * Get the plugin title to be used in admin menu
	 *
	 * @return string
	 */
	function get_plugin_menu_title() {
		return apply_filters( 'as3cf_settings_menu_title', $this->plugin_menu_title );
	}

	/**
	 * Get the plugin prefix.
	 *
	 * @return string
	 */
	public function get_plugin_prefix() {
		return $this->plugin_prefix;
	}

	/**
	 * Get the plugin prefix in slug format, ie. replace underscores with hyphens
	 *
	 * @return string
	 */
	public function get_plugin_prefix_slug() {
		return str_replace( '_', '-', $this->get_plugin_prefix() );
	}

	/**
	 * Get the nonce key for the settings form of the plugin
	 *
	 * @return string
	 */
	function get_settings_nonce_key() {
		return $this->get_plugin_prefix_slug() . '-save-settings';
	}

	/**
	 * Gets arguements used to render a setting view.
	 *
	 * @param string $key
	 *
	 * @return array
	 */
	function get_setting_args( $key ) {
		$is_defined = $this->get_defined_setting( $key, false );

		$args = array(
			'key'           => $key,
			'disabled'      => false,
			'disabled_attr' => '',
			'tr_class'      => 'as3cf-settings-container ' . str_replace( '_', '-', $this->get_plugin_prefix() . '-' . $key . '-container' ),
			'setting_msg'   => '',
			'is_defined'    => false,
		);

		if ( false !== $is_defined ) {
			$args['is_defined']    = true;
			$args['disabled']      = true;
			$args['disabled_attr'] = 'disabled="disabled"';
			$args['tr_class']      .= ' as3cf-defined-setting';
			$args['setting_msg']   = '<span class="as3cf-defined-in-config">' . __( 'defined in wp-config.php', 'as3cf' ) . '</span>';
		}

		return $args;
	}

	/**
	 * Accessor for a plugin setting with conditions to defaults and upgrades
	 *
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function get_setting( string $key, $default = '' ) {
		$settings = $this->get_settings();

		// If legacy setting set, migrate settings
		if (
			isset( $settings['wp-uploads'] ) &&
			$settings['wp-uploads'] &&
			in_array( $key, array( 'copy-to-s3', 'serve-from-s3' ) ) &&
			! isset( $settings[ $key ] )
		) {
			return true;
		}

		// Some settings should default to true on first set up.
		if (
			in_array( $key, array( 'copy-to-s3', 'serve-from-s3' ) ) &&
			! isset( $settings[ $key ] ) &&
			! $this->get_setting( 'bucket' )
		) {
			return true;
		}

		// Don't run upgrade routines on fresh install
		if ( 'post_meta_version' === $key && ! isset( $settings['post_meta_version'] ) ) {
			$routine = self::LATEST_UPGRADE_ROUTINE;

			$this->set_setting( 'post_meta_version', $routine );
			$this->save_settings();

			return $routine;
		}

		// Turn on object versioning by default
		if ( 'object-versioning' == $key && ! isset( $settings['object-versioning'] ) ) {
			return true;
		}

		// Default object prefix
		if ( 'object-prefix' == $key && ! isset( $settings['object-prefix'] ) ) {
			return $this->get_default_object_prefix();
		}

		// Default use year and month folders
		if ( 'use-yearmonth-folders' == $key && ! isset( $settings['use-yearmonth-folders'] ) ) {
			return get_option( 'uploads_use_yearmonth_folders' );
		}

		// Default enable object prefix - enabled unless path is empty in db (defined empty can be intentional, legacy).
		if ( 'enable-object-prefix' == $key ) {
			if ( isset( $settings['enable-object-prefix'] ) && empty( $settings['enable-object-prefix'] ) ) {
				return false;
			}

			if (
				isset( $settings['object-prefix'] ) &&
				empty( $settings['object-prefix'] ) &&
				false === $this->get_defined_setting( 'object-prefix', false )
			) {
				return false;
			} else {
				return true;
			}
		}

		// Region
		if ( false !== ( $region = $this->get_setting_region( $settings, $key, $default ) ) ) {
			return $region;
		}

		// Domain setting since 0.8
		if ( 'domain' === $key && ! isset( $settings['domain'] ) ) {
			if ( $this->get_setting( 'cloudfront' ) ) {
				$domain = 'cloudfront';
			} elseif ( $this->get_setting( 'virtual-host' ) ) {
				$domain = $this->upgrade_virtual_host();
			} else {
				$domain = 'path';
			}

			return $domain;
		}

		// 1.1 Update 'Bucket as Domain' to new CloudFront/Domain UI
		if ( 'domain' === $key && 'virtual-host' === $settings[ $key ] ) {
			return $this->upgrade_virtual_host();
		}

		// SSL radio buttons since 0.8
		if ( 'ssl' == $key && ! isset( $settings['ssl'] ) && ! empty( $this->get_setting( 'force-ssl' ) ) ) {
			if ( $this->get_setting( 'force-ssl', false ) ) {
				$ssl = 'https';
			} else {
				$ssl = 'request';
			}

			$this->set_setting( 'ssl', $ssl );
			$this->remove_setting( 'force-ssl' );
			$this->save_settings();

			return $ssl;
		}

		// Force HTTPS since 1.3
		if ( 'force-https' === $key && ! isset( $settings['force-https'] ) && ! empty( $this->get_setting( 'ssl' ) ) ) {
			$ssl = $this->get_setting( 'ssl', 'request' );

			$force_https = false;
			if ( 'https' === $ssl ) {
				$force_https = true;
			} elseif ( 'http' === $ssl ) {
				$this->maybe_display_deprecated_http_notice();
			}

			$this->set_setting( 'force-https', $force_https );
			$this->remove_setting( 'ssl' );
			$this->save_settings();

			return $force_https;
		}

		// Access Key ID since 2.0.
		if ( 'access-key-id' === $key && ! isset( $settings['access-key-id'] ) && ! empty( $this->get_setting( 'aws-access-key-id' ) ) ) {
			$aws_access_key_id = $this->get_setting( 'aws-access-key-id' );

			$this->set_setting( 'access-key-id', $aws_access_key_id );
			$this->remove_setting( 'aws-access-key-id' );
			$this->save_settings();

			return $aws_access_key_id;
		}

		// Secret Access Key since 2.0.
		if ( 'secret-access-key' === $key && ! isset( $settings['secret-access-key'] ) && ! empty( $this->get_setting( 'aws-secret-access-key' ) ) ) {
			$aws_secret_access_key = $this->get_setting( 'aws-secret-access-key' );

			$this->set_setting( 'secret-access-key', $aws_secret_access_key );
			$this->remove_setting( 'aws-secret-access-key' );
			$this->save_settings();

			return $aws_secret_access_key;
		}

		// Delivery Provider since 2.4.
		if ( 'delivery-provider' === $key && ! isset( $settings['delivery-provider'] ) ) {
			if ( ! empty( $this->get_setting( 'delivery-domain' ) ) ) {
				// Try and guess delivery provider from delivery domain, default to "other" if domain unknown.
				$delivery_provider = 'other';
				$domain            = $this->get_setting( 'cloudfront' );

				if ( strstr( $domain, '.cloudfront.net' ) ) {
					$delivery_provider = AWS_CloudFront::get_provider_key_name();
				} elseif ( strstr( $domain, '.cdn.digitaloceanspaces.com' ) ) {
					$delivery_provider = DigitalOcean_Spaces_CDN::get_provider_key_name();
				} elseif (
					'gcp' === $this->get_storage_provider()->get_provider_key_name() &&
					false === strstr( $domain, $this->get_storage_provider()->get_domain() )
				) {
					$delivery_provider = GCP_CDN::get_provider_key_name();
				}
			} else {
				// No delivery provider, fallback to default, which should equate to delivery via storage provider's defaults.
				$delivery_provider = $default;
			}

			return $delivery_provider;
		}

		// Delivery Domain since 2.4.
		if ( 'enable-delivery-domain' === $key && ! isset( $settings['enable-delivery-domain'] ) ) {
			if ( ! empty( $this->get_setting( 'delivery-domain' ) ) ) {
				return true;
			}

			return false;
		}

		// Delivery Domain since 2.4.
		if ( 'delivery-domain' === $key && ! isset( $settings['delivery-domain'] ) ) {
			if ( 'cloudfront' === $this->get_setting( 'domain' ) && ! empty( $this->get_setting( 'cloudfront' ) ) ) {
				$delivery_domain = $this->get_setting( 'cloudfront' );
			} else {
				// No delivery domain, fallback to storage provider's default domain.
				$delivery_domain = '';
			}

			return $delivery_domain;
		}

		$value = parent::get_setting( $key, $default );

		// Provider
		if ( false !== ( $provider = $this->get_setting_provider( $key, $value ) ) ) {
			return $provider;
		}

		// Bucket
		if ( false !== ( $bucket = $this->get_setting_bucket( $key, $value ) ) ) {
			return $bucket;
		}

		// Use Bucket ACLs
		if ( null !== ( $use_bucket_acls = $this->get_setting_use_bucket_acls( $settings, $key, null ) ) ) {
			return $use_bucket_acls;
		}

		return apply_filters( 'as3cf_setting_' . $key, $value );
	}

	/**
	 * Get the provider and if a constant save to database
	 *
	 * @param string $key
	 * @param string $value
	 * @param string $constant
	 *
	 * @return string|false
	 */
	public function get_setting_provider( $key, $value, $constant = 'AS3CF_PROVIDER' ) {
		if ( 'provider' === $key && defined( $constant ) ) {
			$provider = constant( $constant );

			if ( ! empty( $value ) ) {
				// Clear bucket
				$this->remove_setting( 'provider' );
				$this->save_settings();
			}

			return $provider;
		}

		return false;
	}

	/**
	 * Get the region setting
	 *
	 * @param array  $settings
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return bool|string|WP_Error
	 */
	public function get_setting_region( $settings, $key, $default ) {
		// Region of bucket if not already retrieved
		if ( 'region' === $key && ! isset( $settings['region'] ) ) {
			$region = empty( $default ) ? $this->get_storage_provider()->get_default_region() : $default;
			$bucket = $this->get_setting( 'bucket' );

			if ( $bucket ) {
				$region = $this->get_bucket_region( $bucket );
			}

			if ( is_wp_error( $region ) ) {
				return $region;
			}

			// Store the region for future use
			if ( is_string( $region ) ) {
				parent::set_setting( 'region', $region );
				$this->save_settings();
			}

			return $region;
		}

		// Region of bucket translation
		if ( 'region' === $key && isset( $settings['region'] ) ) {
			return $this->get_storage_provider()->sanitize_region( $settings['region'] );
		}

		return false;
	}

	/**
	 * Get the bucket and if a constant remove from database and clear region
	 *
	 * @param string $key
	 * @param string $value
	 * @param string $constant
	 *
	 * @return string|false
	 */
	public function get_setting_bucket( $key, $value, $constant = 'AS3CF_BUCKET' ) {
		if ( 'bucket' === $key && defined( $constant ) ) {
			$bucket = constant( $constant );

			if ( ! empty( $value ) ) {
				// Clear bucket
				$this->remove_setting( 'bucket' );
				$this->save_settings();
			}

			$this->remove_region_on_constant_change( $bucket, $constant );

			return $bucket;
		}

		return false;
	}

	/**
	 * Remove region on constant change.
	 *
	 * @param string $bucket
	 * @param string $constant
	 */
	private function remove_region_on_constant_change( $bucket, $constant ) {
		$key   = 'as3cf_constant_' . strtolower( $constant );
		$value = get_site_transient( $key );

		if ( false === $value || $bucket !== $value ) {
			set_site_transient( $key, $bucket );
		}

		if ( false !== $value && $bucket !== $value ) {
			$this->bucket_changed();
		}
	}

	/**
	 * If defined bucket has changed, check it once storage provider configured etc.
	 */
	public function process_bucket_change_after_init() {
		add_action( 'as3cf_init', array( $this, 'bucket_changed' ) );
	}

	/**
	 * Perform necessary actions when the chosen bucket is changed.
	 */
	public function bucket_changed() {
		if (
			empty( $this->get_storage_provider() ) ||
			! $this->get_storage_provider()->region_required() ||
			! empty( $this->get_defined_setting( 'region' ) )
		) {
			$this->remove_setting( 'region' );
		}

		$this->remove_setting( 'use-bucket-acls' );
		$this->save_settings();
	}

	/**
	 * Get the derived use-bucket-acls setting
	 *
	 * @param array  $settings
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return bool|null
	 */
	public function get_setting_use_bucket_acls( $settings, $key, $default ) {
		if ( 'use-bucket-acls' === $key && isset( $settings['use-bucket-acls'] ) ) {
			return $settings['use-bucket-acls'];
		}

		if ( 'use-bucket-acls' === $key && ! isset( $settings['use-bucket-acls'] ) ) {
			if ( $this->get_storage_provider()->requires_acls() ) {
				// Got no choice, must use ACLs.
				parent::set_setting( 'use-bucket-acls', true );
				$this->save_settings();

				return true;
			}

			$bucket = $this->get_setting( 'bucket' );
			$region = $this->get_setting( 'region' );

			if ( empty( $bucket ) || empty( $region ) || is_wp_error( $region ) ) {
				return $default;
			}

			$use_bucket_acls = true;

			if ( $this->get_storage_provider()->block_public_access_supported() ) {
				try {
					$public_access_blocked = $this->get_provider_client( $region )->public_access_blocked( $bucket );
				} catch ( Exception $e ) {
					$public_access_blocked = null;
				}

				// At present, we default to using ACLs if public access to bucket status unknown.
				if ( true === $public_access_blocked ) {
					$use_bucket_acls = false;
				}
			}

			if ( $use_bucket_acls && $this->get_storage_provider()->object_ownership_supported() ) {
				try {
					$object_ownership_enforced = $this->get_provider_client( $region )->object_ownership_enforced( $bucket );
				} catch ( Exception $e ) {
					$object_ownership_enforced = null;
				}

				// At present, we default to using ACLs if object ownership enforcement status unknown.
				if ( true === $object_ownership_enforced ) {
					$use_bucket_acls = false;
				}
			}

			parent::set_setting( 'use-bucket-acls', $use_bucket_acls );
			$this->save_settings();

			return $use_bucket_acls;
		}

		return $default;
	}

	/**
	 * Setter for a plugin setting with custom hooks
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function set_setting( $key, $value ) {
		$value = apply_filters( 'as3cf_set_setting_' . $key, $value );

		// Remove disallowed characters from custom domain
		// TODO: Remove cloudfront once no longer ever being saved.
		if ( 'delivery-domain' === $key || 'cloudfront' === $key ) {
			$value = AS3CF_Utils::sanitize_custom_domain( $value );
		}

		parent::set_setting( $key, $value );
	}

	/**
	 * Return the default object prefix
	 *
	 * @return string
	 */
	public function get_default_object_prefix() {
		if ( is_multisite() ) {
			return 'wp-content/uploads/';
		}

		$uploads = wp_upload_dir();
		$parts   = parse_url( $uploads['baseurl'] );
		$path    = ltrim( $parts['path'], '/' );

		return trailingslashit( $path );
	}

	/**
	 * Allowed mime types array that can be edited for specific provider uploading
	 *
	 * @return array
	 */
	public function get_allowed_mime_types() {
		/**
		 * Filters list of allowed mime types and file extensions for uploading
		 *
		 * @param array $types Mime types keyed by the file extension regex corresponding to those types.
		 */
		return apply_filters( 'as3cf_allowed_mime_types', get_allowed_mime_types() );
	}

	/**
	 * Wrapper for scheduling  cron jobs
	 *
	 * @param string      $hook
	 * @param null|string $interval Defaults to hook if not supplied
	 * @param array       $args
	 */
	public function schedule_event( $hook, $interval = null, $args = array() ) {
		if ( is_null( $interval ) ) {
			$interval = $hook;
		}

		// Always schedule events on primary blog
		$this->switch_to_blog();

		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time(), $interval, $hook, $args );
		}

		$this->restore_current_blog();
	}

	/**
	 * Wrapper for clearing scheduled events for a specific cron job
	 *
	 * @param string $hook
	 */
	public function clear_scheduled_event( $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}

		if ( is_multisite() ) {
			// Always clear schedule events on primary blog
			$this->switch_to_blog();

			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}

			$this->restore_current_blog();
		}
	}

	/**
	 * Get local URL preview.
	 *
	 * @param string $suffix
	 *
	 * @return string
	 */
	protected function get_local_url_preview( $suffix = 'example.jpg' ) {
		$uploads = wp_upload_dir();

		return trailingslashit( $uploads['url'] ) . $suffix;
	}

	/**
	 * Generate a preview of the URL of files uploaded to provider
	 *
	 * @param bool        $get_parts Return array of title and example parts, default false
	 * @param string|null $suffix    Default 'example.jpg'
	 * @param array|null  $settings  Optional full set of settings to be used instead of saved settings
	 *
	 * @return string|array
	 */
	public function get_url_preview( bool $get_parts = false, string $suffix = null, array $settings = null ) {
		if (
			! empty( $settings ) &&
			empty( array_diff( $this->get_allowed_settings_keys(), array_keys( $settings ) ) )
		) {
			$this->set_settings( $settings );
		}

		if ( empty( $suffix ) || ! is_string( $suffix ) ) {
			$suffix = 'example.jpg';
		}

		$bucket_path = AS3CF_Utils::trailingslash_prefix( $this->get_simple_file_prefix() ) . $suffix;

		$as3cf_item = new Media_Library_Item(
			$this->get_storage_provider()->get_provider_key_name(),
			$this->get_setting( 'region' ),
			$this->get_setting( 'bucket' ),
			$bucket_path,
			false,
			null,
			$bucket_path
		);

		$url = $as3cf_item->get_provider_url();

		if ( is_wp_error( $url ) ) {
			return $get_parts ? array() : '';
		}

		if ( ! $get_parts ) {
			return $url;
		}

		/*
		 * As URLs are reformatted by each storage/delivery provider,
		 * we need to deconstruct the returned URL to determine the structure
		 * and assign them to the correct parts.
		 */
		$url_parts = array();

		$parts = parse_url( $url );

		if ( empty( $parts ) ) {
			return $url_parts;
		}

		// Scheme
		if ( ! empty( $parts['scheme'] ) ) {
			$url_parts[] = array(
				'key'     => 'scheme',
				'title'   => _x( 'Scheme', 'URL part', 'amazon-s3-and-cloudfront' ),
				'example' => $parts['scheme'] . '://',
			);
		}

		// Domain
		if ( ! empty( $parts['host'] ) ) {
			$domain = $parts['host'];

			if ( empty( $parts['port'] ) ) {
				$domain .= '/';
			}

			$url_parts[] = array(
				'key'     => 'domain',
				'title'   => _x( 'Domain', 'URL part', 'amazon-s3-and-cloudfront' ),
				'example' => $domain,
			);
		}

		// Port: not usually used, but could be for custom storage/delivery setups.
		if ( ! empty( $parts['port'] ) ) {
			$url_parts[] = array(
				'key'     => 'port',
				'title'   => _x( 'Port', 'URL part', 'amazon-s3-and-cloudfront' ),
				'example' => ':' . $parts['port'] . '/',
			);
		}

		// Bucket: in some scenarios the bucket is added to the path.
		// To determine whether that is the case, we need to get the position
		// of the Object Prefix, Year/Month, Object Versioning and suffix segments.
		if ( ! empty( $parts['path'] ) ) {
			$path = untrailingslashit( substr( $parts['path'], 0, -strlen( $suffix ) ) );

			if ( $this->get_setting( 'object-versioning' ) ) {
				$prev_slash_pos = strrpos( $path, '/' );
				$object_version = substr( $path, $prev_slash_pos + 1 );
				$path           = substr( $path, 0, $prev_slash_pos );
			}

			if ( $this->get_setting( 'use-yearmonth-folders' ) ) {
				$year_month = substr( $path, -7 );
				$path       = untrailingslashit( substr( $path, 0, -7 ) );
			}

			if ( $this->get_setting( 'enable-object-prefix' ) ) {
				$object_prefix = AS3CF_Utils::unleadingslashit( untrailingslashit( $this->get_setting( 'object-prefix' ) ) );
				$path          = untrailingslashit( substr( $path, 0, -strlen( $object_prefix ) ) );
			}
		}

		if ( ! empty( $path ) ) {
			$bucket      = AS3CF_Utils::unleadingslashit( untrailingslashit( $path ) );
			$url_parts[] = array(
				'key'     => 'bucket',
				'title'   => _x( 'Bucket', 'URL part', 'amazon-s3-and-cloudfront' ),
				'example' => $bucket . '/',
			);
		}

		if ( ! empty( $object_prefix ) ) {
			$url_parts[] = array(
				'key'     => 'object-prefix',
				'title'   => _x( 'Prefix', 'URL part', 'amazon-s3-and-cloudfront' ),
				'example' => $object_prefix . '/',
			);
		}

		if ( ! empty( $year_month ) ) {
			$url_parts[] = array(
				'key'     => 'year-month',
				'title'   => _x( 'Year & Month', 'URL part', 'amazon-s3-and-cloudfront' ),
				'example' => $year_month . '/',
			);
		}

		if ( ! empty( $object_version ) ) {
			$url_parts[] = array(
				'key'     => 'object-version',
				'title'   => _x( 'Version', 'URL part', 'amazon-s3-and-cloudfront' ),
				'example' => $object_version . '/',
			);
		}

		$url_parts[] = array(
			'key'     => 'filename',
			'title'   => _x( 'Filename', 'URL part', 'amazon-s3-and-cloudfront' ),
			'example' => $suffix,
		);

		return $url_parts;
	}

	/**
	 * Remove access keys from saved settings if a key constant is defined.
	 */
	public function remove_access_keys_if_constants_set() {
		if ( AWS_Provider::is_any_access_key_constant_defined() ) {
			$this->remove_access_keys();
		}
	}

	/**
	 * Remove access keys from settings.
	 */
	protected function remove_access_keys() {
		$this->remove_setting( 'access-key-id' );
		$this->remove_setting( 'secret-access-key' );
		$this->save_settings();
	}

	/**
	 * Get the object versioning string prefix
	 *
	 * @return string
	 */
	function get_object_version_string() {
		if ( $this->get_setting( 'use-yearmonth-folders' ) ) {
			$date_format = 'dHis';
		} else {
			$date_format = 'YmdHis';
		}

		// Use current time so that object version is unique
		$time = current_time( 'timestamp' );

		$object_version = date( $date_format, $time ) . '/';
		$object_version = apply_filters( 'as3cf_get_object_version_string', $object_version );

		return $object_version;
	}

	/**
	 * Does file exist
	 *
	 * @param string $filename
	 * @param string $time
	 *
	 * @return bool
	 */
	function does_file_exist( $filename, $time ) {
		if ( $this->does_file_exist_local( $filename, $time ) ) {
			return true;
		}

		if ( ! $this->get_setting( 'object-versioning' ) && $this->does_file_exist_provider( $filename, $time ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Does file exist local
	 *
	 * @param string $filename
	 * @param string $time
	 *
	 * @return bool
	 */
	function does_file_exist_local( $filename, $time ) {
		global $wpdb;

		$path = wp_upload_dir( $time );
		$path = ltrim( $path['subdir'], '/' );

		if ( '' !== $path ) {
			$path = trailingslashit( $path );
		}
		$file = $path . $filename;

		// WordPress doesn't check its own basic record, so we will.
		$sql = $wpdb->prepare( "
			SELECT COUNT(*)
			FROM $wpdb->postmeta
			WHERE meta_key = %s
			AND meta_value = %s
		", '_wp_attached_file', $file );

		if ( (bool) $wpdb->get_var( $sql ) ) {
			return true;
		}

		// Check our records of local source path as it also covers original_image.
		if ( ! empty( Media_Library_Item::get_by_source_path( array( $file ), array(), true, true ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Does file exist provider
	 *
	 * @param string $filename
	 * @param string $time
	 *
	 * @return bool
	 * @throws Exception
	 */
	function does_file_exist_provider( $filename, $time ) {
		$bucket = $this->get_setting( 'bucket' );
		$region = $this->get_setting( 'region' );

		if ( is_wp_error( $region ) ) {
			return false;
		}

		$provider_client = $this->get_provider_client( $region );
		$prefix          = AS3CF_Utils::trailingslash_prefix( $this->get_object_prefix() );
		$prefix          .= AS3CF_Utils::trailingslash_prefix( $this->get_dynamic_prefix( $time ) );

		return $provider_client->does_object_exist( $bucket, $prefix . $filename );
	}

	/**
	 * Generate unique filename
	 *
	 * @param string $name
	 * @param string $ext
	 * @param string $time
	 *
	 * @return string
	 */
	function generate_unique_filename( $name, $ext, $time ) {
		$count    = 1;
		$filename = $name . '-' . $count . $ext;

		while ( $this->does_file_exist( $filename, $time ) ) {
			$count++;
			$filename = $name . '-' . $count . $ext;
		}

		return $filename;
	}

	/**
	 * Check the plugin is correctly set up.
	 *
	 * @param bool $with_credentials Do provider credentials need to be set up too? Defaults to false.
	 *
	 * @return bool
	 */
	public function is_plugin_setup( $with_credentials = false ) {
		if ( $with_credentials && $this->get_storage_provider()->needs_access_keys() ) {
			// AWS not configured
			return false;
		}

		if ( false === (bool) $this->get_setting( 'bucket' ) ) {
			// No bucket selected
			return false;
		}

		if ( is_wp_error( $this->get_setting( 'region' ) ) ) {
			// Region error when retrieving bucket location
			return false;
		}

		// All good, let's do this
		return true;
	}

	/**
	 * Return the scheme to be used in URLs
	 *
	 * @param bool|null $use_ssl
	 *
	 * @return string
	 */
	function get_url_scheme( $use_ssl = null ) {
		if ( $this->use_ssl( $use_ssl ) ) {
			$scheme = 'https';
		} else {
			$scheme = 'http';
		}

		return $scheme;
	}

	/**
	 * Determine when to use https in URLS
	 *
	 * @param bool|null $use_ssl
	 *
	 * @return bool
	 */
	public function use_ssl( $use_ssl = null ) {
		if ( is_ssl() ) {
			$use_ssl = true;
		}

		if ( ! is_bool( $use_ssl ) ) {
			$use_ssl = $this->get_setting( 'force-https' );
		}

		if ( empty( $use_ssl ) ) {
			$use_ssl = false;
		}

		return apply_filters( 'as3cf_use_ssl', $use_ssl );
	}

	/**
	 * Get the custom object prefix if enabled
	 *
	 * @param string $toggle_setting
	 *
	 * @return string
	 */
	public function get_object_prefix( string $toggle_setting = 'enable-object-prefix' ): string {
		if ( $this->get_setting( $toggle_setting ) ) {
			return trailingslashit( trim( $this->get_setting( 'object-prefix' ) ) );
		}

		return '';
	}

	/**
	 * Is attachment served by provider.
	 *
	 * @param int                   $attachment_id
	 * @param bool                  $skip_rewrite_check          Still check if offloaded even if not currently rewriting URLs? Default: false
	 * @param bool                  $skip_current_provider_check Skip checking if offloaded to current provider. Default: false, negated if $provider supplied
	 * @param Storage_Provider|null $provider                    Provider where attachment expected to be offloaded to. Default: currently configured provider
	 * @param bool                  $check_is_verified           Check that metadata is verified, has no effect if $skip_rewrite_check is true. Default: false
	 *
	 * @return bool|Media_Library_Item
	 */
	public function is_attachment_served_by_provider( $attachment_id, $skip_rewrite_check = false, $skip_current_provider_check = false, Storage_Provider $provider = null, $check_is_verified = false ) {
		if ( ! $skip_rewrite_check && ! $this->get_setting( 'serve-from-s3' ) ) {
			// Not serving provider URLs
			return false;
		}

		$as3cf_item = Media_Library_Item::get_by_source_id( $attachment_id );

		if ( ! $as3cf_item ) {
			// File not uploaded to a provider
			return false;
		}

		if ( ! $skip_rewrite_check && ! empty( $check_is_verified ) && ! $as3cf_item->is_verified() ) {
			// Offload not verified, treat as not offloaded.
			return false;
		}

		if ( ! $skip_current_provider_check && empty( $provider ) ) {
			$provider = $this->get_storage_provider();
		}

		if ( ! empty( $provider ) && $provider::get_provider_key_name() !== $as3cf_item->provider() ) {
			// File not uploaded to required provider
			return false;
		}

		return $as3cf_item;
	}

	/**
	 * Helper method for returning data to AJAX call
	 *
	 * @param array $return
	 */
	function end_ajax( $return = array() ) {
		wp_send_json( $return );
	}

	/**
	 * Ensure AJAX request from expected route and user with capability to handle offloaded media.
	 *
	 * @param string $capability Defaults to 'manage_options'.
	 * @param bool   $return
	 *
	 * @return bool
	 */
	function verify_ajax_request( $capability = null, $return = false ) {
		$capability = empty( $capability ) ? 'manage_options' : $capability;

		if ( ! is_admin() ) { // input var okay
			$msg = __( 'This action can only be performed through an admin screen.', 'amazon-s3-and-cloudfront' );
		} elseif ( empty( $_POST['_nonce'] ) || empty( $_POST['action'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_nonce'] ), sanitize_key( $_POST['action'] ) ) ) { // input var okay
			$msg = __( 'Cheatin&#8217; eh?', 'amazon-s3-and-cloudfront' );
		} elseif ( ! current_user_can( $capability ) ) {
			$msg = __( 'You do not have sufficient permissions to access this page.', 'amazon-s3-and-cloudfront' );
		}

		if ( ! empty( $msg ) ) {
			AS3CF_Error::log( $msg );

			if ( $return ) {
				return false;
			} else {
				wp_die( $msg );
			}
		}

		return true;
	}

	/**
	 * Returns cleaned up bucket name or returns false if missing.
	 *
	 * @param string $bucket
	 *
	 * @return string|bool
	 */
	function check_bucket( $bucket ) {
		$bucket = sanitize_text_field( $bucket );

		return empty( $bucket ) ? false : strtolower( $bucket );
	}

	/**
	 * Create an S3 bucket
	 *
	 * @param string      $bucket_name
	 * @param bool|string $region option location constraint
	 *
	 * @return bool|WP_Error
	 */
	function create_bucket( $bucket_name, $region = false ) {
		try {
			$args = array( 'Bucket' => $bucket_name );

			if ( defined( 'AS3CF_REGION' ) ) {
				// Make sure we always use the defined region
				$region = AS3CF_REGION;
			}

			if ( ! is_null( $region ) && $this->get_storage_provider()->get_default_region() !== $region ) {
				$args['LocationConstraint'] = $region;
			}

			$this->get_provider_client( $region )->create_bucket( $args );
		} catch ( Exception $e ) {
			return new WP_Error( 'exception', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Add the settings page to the top-level Settings menu item.
	 */
	public function admin_menu() {
		$this->hook_suffix = add_submenu_page(
			$this->get_plugin_pagenow(),
			$this->get_plugin_page_title(),
			$this->get_plugin_menu_title(),
			'manage_options',
			$this->plugin_slug,
			array( $this, 'render_page' )
		);

		do_action( 'as3cf_hook_suffix', $this->hook_suffix );

		add_action( 'load-' . $this->hook_suffix, array( $this, 'plugin_load' ) );
	}

	/**
	 * In our settings screen?
	 *
	 * @param WP_Screen|null $screen
	 *
	 * @return bool
	 */
	public function our_screen( WP_Screen $screen = null ) {
		if ( ! is_admin() || empty( $this->hook_suffix ) ) {
			return false;
		}

		if ( empty( $screen ) ) {
			$screen = get_current_screen();
		}

		if ( empty( $screen ) || false === strpos( $screen->id, $this->hook_suffix ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Add the settings page to the top-level AWS menu item for backwards compatibility.
	 *
	 * @param Amazon_Web_Services $aws Plugin class instance from the amazon-web-services plugin.
	 */
	public function aws_admin_menu( $aws ) {
		$aws->add_page(
			$this->get_plugin_page_title(),
			$this->get_plugin_menu_title(),
			'manage_options',
			$this->plugin_slug,
			array( $this, 'render_page' )
		);
	}

	/**
	 * What is the default storage provider for legacy data?
	 *
	 * @return string
	 */
	public static function get_default_storage_provider() {
		return static::$default_storage_provider;
	}

	/**
	 * What is the default delivery provider for legacy data?
	 *
	 * @return string
	 */
	public static function get_default_delivery_provider() {
		return static::$default_delivery_provider;
	}

	/**
	 * Returns the Provider's default region slug.
	 *
	 * @return string
	 */
	public function get_default_region() {
		return $this->get_storage_provider()->get_default_region();
	}

	/**
	 * Get the S3 client
	 *
	 * @param bool|string $region specify region to client for signature
	 * @param bool        $force  force return of new provider client when swapping regions
	 *
	 * @return Storage_Provider|Null_Provider
	 */
	public function get_provider_client( $region = false, $force = false ) {
		if (
			is_null( $this->provider_client ) ||
			is_null( $this->provider_client_region ) ||
			$force ||
			( false !== $region && $this->provider_client_region !== $region )
		) {
			$args = array();

			if ( $force ) {
				$this->set_storage_provider();
			}

			if ( $region ) {
				$args['region'] = $this->get_storage_provider()->sanitize_region( $region );
			} elseif ( $this->storage_provider::region_required() ) {
				// Region isn't provided but the provider requires one, make a best effort
				$args['region'] = $this->get_storage_provider()->sanitize_region( $this->get_setting( 'region' ) );
			}

			$provider_client_region = isset( $args['region'] ) ? $args['region'] : $region;

			try {
				$this->set_client( $this->get_storage_provider()->get_client( $args ), $provider_client_region );
			} catch ( Exception $e ) {
				AS3CF_Error::log( $e->getMessage() );
				$this->set_client( new Null_Provider() );
			}
		}

		return $this->provider_client;
	}

	/**
	 * Setter for Provider client
	 *
	 * @param Storage_Provider|Null_Provider $client
	 * @param bool|string                    $region
	 */
	public function set_client( $client, $region = false ) {
		$this->provider_client = $client;

		if ( false !== $region ) {
			$this->provider_client_region = $region;
		}
	}

	/**
	 * Get the region of a bucket
	 *
	 * @param string  $bucket
	 * @param boolean $use_cache defaults to true
	 *
	 * @return string|WP_Error
	 */
	public function get_bucket_region( $bucket, $use_cache = true ) {
		$regions = get_site_transient( 'as3cf_regions_cache' );

		if ( ! is_array( $regions ) ) {
			$regions = array();
		}

		if ( $use_cache && isset( $regions[ $bucket ] ) ) {
			return $regions[ $bucket ];
		}

		$this->notices->dismiss_notice( 'bucket-error' );

		// If we don't have any credentials, don't attempt region check and don't cache error.
		if ( $this->get_storage_provider()->needs_access_keys() ) {
			return new WP_Error( 'exception', $this->get_storage_provider()->get_needs_access_keys_desc() );
		}

		try {
			$region = $this->get_provider_client( false, true )->get_bucket_location( array( 'Bucket' => $bucket ) );
		} catch ( Exception $e ) {
			$bucket_error = $this->get_storage_provider()->prepare_bucket_error( new WP_Error( 'exception', $e->getMessage() ) );
			$error_msg    = sprintf(
				__( '<strong>Error Getting Bucket Region</strong> &mdash; There was an error attempting to get the region of the bucket %1$s: %2$s', 'amazon-s3-and-cloudfront' ),
				$bucket,
				$bucket_error
			);

			$dismissible = $this->saving_settings();
			$this->notices->add_notice(
				$error_msg,
				array(
					'type'                  => 'error',
					'dismissible'           => $dismissible,
					'only_show_in_settings' => true,
					'only_show_on_tab'      => 'media',
					'hide_on_parent'        => true,
					'custom_id'             => 'bucket-error',
					'short'                 => $bucket_error,
				)
			);

			$region = new WP_Error( 'exception', $error_msg );
		}

		if ( ! is_wp_error( $region ) ) {
			$region = $this->get_storage_provider()->sanitize_region( $region );
		}

		$regions[ $bucket ] = $region;
		set_site_transient( 'as3cf_regions_cache', $regions, 5 * MINUTE_IN_SECONDS );

		return $region;
	}

	/**
	 * Get a list of buckets from S3
	 *
	 * @param bool $region
	 *
	 * @return array|WP_Error - list of buckets
	 */
	function get_buckets( $region = false ) {
		try {
			$result = $this->get_provider_client( $region )->list_buckets();
		} catch ( Exception $e ) {
			$bucket_error = $this->get_storage_provider()->prepare_bucket_error( new WP_Error( 'exception', $e->getMessage() ) );
			$error_msg    = sprintf(
				__( '<strong>Error Getting Buckets</strong> &mdash; %s', 'amazon-s3-and-cloudfront' ),
				$bucket_error
			);

			return new WP_Error( 'exception', $error_msg );
		}

		if ( empty( $result['Buckets'] ) ) {
			return array();
		} else {
			return $result['Buckets'];
		}
	}

	/**
	 * Checks the user can write to the bucket.
	 *
	 * @param string $bucket
	 * @param string $region
	 * @param bool   $force
	 *
	 * @return bool|WP_Error
	 */
	public function check_write_permission( $bucket = null, $region = null, bool $force = false ) {
		if ( $this->get_storage_provider()->needs_access_keys() ) {
			// If no access keys set, then no need to check.
			return false;
		}

		$bucket = is_null( $bucket ) ? $this->get_setting( 'bucket' ) : $bucket;

		// If no bucket set, then no need to check.
		if ( empty( $bucket ) ) {
			return false;
		}

		// need to set region for buckets in non default region
		if ( is_null( $region ) ) {
			$region = $this->get_setting( 'region' );

			if ( is_wp_error( $region ) ) {
				return $region;
			}
		}

		// Create a 32 character hash of the provider/region/bucket to use in transients.
		$hash = hash( 'md5', $this->get_storage_provider()->get_provider_key_name() . $region . $bucket );

		if ( ! $force && isset( self::$buckets_check[ $hash ] ) ) {
			return self::$buckets_check[ $hash ];
		}

		// Guard against checking a validated bucket more than once per day.
		$can_write = get_site_transient( $this->get_plugin_prefix() . '_can_write_' . $hash );

		if ( $force || ( ! $can_write && ! get_site_transient( $this->get_plugin_prefix() . '_can_write_checked_' . $hash ) ) ) {
			// Guard against checking an invalid bucket more than once every two minutes.
			set_site_transient( $this->get_plugin_prefix() . '_can_write_checked_' . $hash, true, 2 * MINUTE_IN_SECONDS );

			$key           = $this->get_simple_file_prefix() . 'as3cf-permission-check.txt';
			$file_contents = __( 'This is a test file to check if the user has write permission to the bucket. Delete me if found.', 'amazon-s3-and-cloudfront' );

			try {
				$can_write = $this->get_provider_client( $region, true )->can_write( $bucket, $key, $file_contents );
			} catch ( Exception $e ) {
				AS3CF_Error::log( $e->getMessage() );

				return new WP_Error( 'exception', $e->getMessage() );
			}

			// If we get back an unexpected error message, throw an error.
			if ( is_string( $can_write ) ) {
				$error_msg = sprintf( __( 'There was an error attempting to check the permissions of the bucket %s: %s', 'amazon-s3-and-cloudfront' ), $bucket, $can_write );
				AS3CF_Error::log( $error_msg );

				return new WP_Error( 'exception', $error_msg );
			}

			set_site_transient( $this->get_plugin_prefix() . '_can_write_' . $hash, $can_write, DAY_IN_SECONDS );
		}

		self::$buckets_check[ $hash ] = $can_write;

		return $can_write;
	}

	/**
	 * Is the given or currently configured bucket and region combination writable?
	 *
	 * This is a convenience wrapper for `check_permission` that forces a boolean response.
	 *
	 * @param string|null $bucket
	 * @param string|null $region
	 * @param bool        $force
	 *
	 * @return bool
	 */
	public function bucket_writable( string $bucket = null, string $region = null, bool $force = false ): bool {
		$bucket_writable = $this->check_write_permission( $bucket, $region, $force );

		if ( is_wp_error( $bucket_writable ) ) {
			$bucket_writable = false;
		} else {
			$bucket_writable = (bool) $bucket_writable;
		}

		return $bucket_writable;
	}

	/**
	 * Get settings validation status.
	 *
	 * @return array
	 */
	public function settings_validation_status(): array {
		$force = isset( $_REQUEST['revalidateSettings'] ) && 'true' === $_REQUEST['revalidateSettings'];

		// If we're in the middle of saving settings, don't force.
		$force = $this->saving_settings() ? false : $force;

		return $this->validation_manager->validate( $force );
	}

	/**
	 * Get the file prefix for test and display purposes.
	 *
	 * Note: This should only be used for "naive" prefix calculations for
	 * display and write permission test purposes
	 *
	 * @param null|string $time
	 * @param bool        $object_versioning_allowed Can an Object Versioning string be appended if setting turned on? Default true.
	 *
	 * @return string
	 */
	private function get_simple_file_prefix( $time = null, $object_versioning_allowed = true ) {
		$prefix = AS3CF_Utils::trailingslash_prefix( $this->get_object_prefix() );
		$prefix .= AS3CF_Utils::trailingslash_prefix( $this->get_dynamic_prefix( $time ) );

		if ( ! empty( $object_versioning_allowed ) && $this->get_setting( 'object-versioning' ) ) {
			$prefix .= AS3CF_Utils::trailingslash_prefix( $this->get_object_version_string() );
		}

		return $prefix;
	}

	/**
	 * Register modal scripts and styles so they can be enqueued later
	 *
	 * TODO: Could be replaced with Modal.svelte using REST-API for modal button's actions etc.
	 */
	public function register_modal_assets() {
		$version = $this->get_asset_version();
		$suffix  = $this->get_asset_suffix();

		$src = plugins_url( 'assets/css/modal.css', $this->plugin_file_path );
		wp_register_style( 'as3cf-modal', $src, array(), $version );

		$src = plugins_url( 'assets/js/modal' . $suffix . '.js', $this->plugin_file_path );
		wp_register_script( 'as3cf-modal', $src, array( 'jquery' ), $version, true );
	}

	/**
	 * Enqueue assets needed for settings UI.
	 *
	 * @param array $config Initial settings.
	 */
	protected function load_settings_assets( $config = array() ) {
		$this->enqueue_style( 'as3cf-settings', 'assets/css/settings' );
		$this->enqueue_script( 'as3cf-settings', 'assets/js/settings', array(), false );

		wp_localize_script( 'as3cf-settings',
			'as3cf_settings',
			$config
		);
	}

	/**
	 * On plugin load.
	 */
	public function plugin_load() {
		/*
		 * If the request is using the old parent page for the settings page, (i.e. in AWS menu)
		 * redirect to the new one. Unfortunately, there is no way to preserve the hash, if present.
		 * This works because the hook suffix is the same for both, regardless of parent page.
		 */
		if ( $this->get_plugin_pagenow() !== $GLOBALS['pagenow'] ) {
			wp_redirect( $this->get_plugin_page_url() );
			exit;
		}

		add_action( 'network_admin_notices', array( $this, 'settings_saved_notice' ) );

		// Base style for settings page.
		$this->enqueue_style( 'as3cf-style', 'assets/css/style' );

		$remove_local_link = static::more_info_link( '/wp-offload-media/doc/compatibility-with-other-plugins/', 'error-media+remove+files+from+server' );
		$remove_local_msg  = sprintf( __( '<strong>Warning</strong> &mdash; Some plugins depend on the file being present on the local server and may not work when the file is removed. %s', 'amazon-s3-and-cloudfront' ), $remove_local_link );
		$remove_local_msg  .= "<br/><br />";
		$remove_local_msg  .= __( 'If you have a backup system in place (as you should) that backs up your site files, media, and database, your media will no longer be backed up as it will no longer be present on the filesystem.', 'amazon-s3-and-cloudfront' );

		$config = array(
			'strings'                          => array(
				'needs_access_keys'                     => Storage_Provider::get_needs_access_keys_desc(),
				'create_bucket_error'                   => __( 'Error creating bucket', 'amazon-s3-and-cloudfront' ),
				'create_bucket_name_missing'            => __( 'Bucket name not entered.', 'amazon-s3-and-cloudfront' ),
				'create_bucket_name_short'              => __( 'Bucket name too short.', 'amazon-s3-and-cloudfront' ),
				'create_bucket_name_long'               => __( 'Bucket name too long.', 'amazon-s3-and-cloudfront' ),
				'create_bucket_invalid_chars'           => __( 'Invalid character. Bucket names can contain lowercase letters, numbers, periods and hyphens.', 'amazon-s3-and-cloudfront' ),
				'select_bucket_invalid_chars'           => __( 'Invalid character. Bucket names can contain lowercase letters, numbers, periods and hyphens. Legacy buckets may also include uppercase letters and underscores.', 'amazon-s3-and-cloudfront' ),
				'no_bucket_selected'                    => __( 'No bucket selected.', 'amazon-s3-and-cloudfront' ),
				'defined_region_invalid'                => __( 'Invalid region defined in wp-config.', 'amazon-s3-and-cloudfront' ),
				'save_bucket_error'                     => __( 'Error saving bucket', 'amazon-s3-and-cloudfront' ),
				'get_buckets_error'                     => __( 'Error fetching buckets', 'amazon-s3-and-cloudfront' ),
				'get_url_preview_error'                 => __( 'Error getting URL preview: ', 'amazon-s3-and-cloudfront' ),
				'save_alert'                            => __( 'The changes you made will be lost if you navigate away from this page', 'amazon-s3-and-cloudfront' ),
				'api_error_notice_heading'              => __( 'Error From Server', 'amazon-s3-and-cloudfront' ),
				'get_diagnostic_info'                   => __( 'Getting diagnostic info…', 'amazon-s3-and-cloudfront' ),
				'get_diagnostic_info_error'             => __( 'Error getting diagnostic info: ', 'amazon-s3-and-cloudfront' ),
				'not_shown_placeholder'                 => _x( '-- not shown --', 'placeholder for hidden access key, 39 char max', 'amazon-s3-and-cloudfront' ),
				'defined_in_wp_config'                  => __( 'Defined in wp-config.php', 'amazon-s3-and-cloudfront' ),
				'settings_locked'                       => __( 'Settings locked', 'amazon-s3-and-cloudfront' ),
				'needs_refresh'                         => sprintf(
					__( '<strong>Settings Locked</strong> &mdash; Settings have been changed by someone else, please <a href="%s">refresh the page</a>.', 'amazon-s3-and-cloudfront' ),
					$this->get_plugin_page_url()
				),
				'get_licence_discount_text'             => __( 'Get up to 40% off', 'amazon-s3-and-cloudfront' ),
				// Settings
				'change'                                => _x( 'Change', 'Change link title', 'amazon-s3-and-cloudfront' ),
				'edit'                                  => _x( 'Edit', 'Edit button text', 'amazon-s3-and-cloudfront' ),
				'toggle'                                => _x( 'Toggle', 'Toggle switch fallback text', 'amazon-s3-and-cloudfront' ),
				'back'                                  => _x( 'Back', 'Back button text', 'amazon-s3-and-cloudfront' ),
				'skip'                                  => _x( 'Skip', 'Skip button text', 'amazon-s3-and-cloudfront' ),
				'next'                                  => _x( 'Next', 'Next button text', 'amazon-s3-and-cloudfront' ),
				'yes'                                   => _x( 'Yes', 'Yes button text', 'amazon-s3-and-cloudfront' ),
				'no'                                    => _x( 'No', 'No button text', 'amazon-s3-and-cloudfront' ),
				'help_desc'                             => _x( 'Click to view help doc on our site', 'Help icon alt text', 'amazon-s3-and-cloudfront' ),
				'selected_desc'                         => _x( 'Option selected', 'Selected option icon alt text', 'amazon-s3-and-cloudfront' ),
				'media_tab_title'                       => _x( 'Media', 'Tab title', 'amazon-s3-and-cloudfront' ),
				'assets_tab_title'                      => _x( 'Assets', 'Tab title', 'amazon-s3-and-cloudfront' ),
				'tools_tab_title'                       => _x( 'Tools', 'Tab title', 'amazon-s3-and-cloudfront' ),
				'support_tab_title'                     => _x( 'Support', 'Tab title', 'amazon-s3-and-cloudfront' ),
				'loading'                               => __( 'Loading…', 'amazon-s3-and-cloudfront' ),
				'nothing_found'                         => __( 'Nothing Found', 'amazon-s3-and-cloudfront' ),
				'save_changes'                          => _x( 'Save Changes', 'Button text', 'amazon-s3-and-cloudfront' ),
				'cancel_button'                         => _x( 'Cancel', 'Button text', 'amazon-s3-and-cloudfront' ),
				'save_and_continue'                     => _x( 'Save & Continue', 'Button text', 'amazon-s3-and-cloudfront' ),
				// OffloadStatus
				'offloaded'                             => _x( 'Offloaded', 'Label in nav bar status indicator', 'amazon-s3-and-cloudfront' ),
				'show_details'                          => _x( 'Show Details', 'Button title', 'amazon-s3-and-cloudfront' ),
				'hide_details'                          => _x( 'Hide Details', 'Button title', 'amazon-s3-and-cloudfront' ),
				'offload_status_title'                  => _x( 'Offload Status', 'Panel title', 'amazon-s3-and-cloudfront' ),
				'refresh_title'                         => _x( 'Refresh', 'Button title', 'amazon-s3-and-cloudfront' ),
				'refresh_media_counts_desc'             => _x( 'Force refresh all media counts, may be resource intensive.', 'Button description', 'amazon-s3-and-cloudfront' ),
				'summary_type_title'                    => _x( 'Source', 'Column title', 'amazon-s3-and-cloudfront' ),
				'summary_offloaded_title'               => _x( 'Offloaded', 'Column title', 'amazon-s3-and-cloudfront' ),
				'summary_not_offloaded_title'           => _x( 'Not Offloaded', 'Column title', 'amazon-s3-and-cloudfront' ),
				'summary_total_row_title'               => _x( 'Total', 'Row title', 'amazon-s3-and-cloudfront' ),
				'offload_remaining_upsell_cta'          => _x( 'Upgrade now', 'Upsell call to action', 'amazon-s3-and-cloudfront' ),
				'no_media'                              => _x( 'There are no media items', 'Status message', 'amazon-s3-and-cloudfront' ),
				'all_media_offloaded'                   => _x( 'All media items have been offloaded', 'Status message', 'amazon-s3-and-cloudfront' ),
				// MediaLibraryPage
				'url_preview_title'                     => _x( 'URL Preview', 'Section title', 'amazon-s3-and-cloudfront' ),
				'url_preview_desc'                      => _x( 'When a media URL is rewritten, it will use the following structure based on the current Storage and Delivery settings:', 'Description of URL Preview', 'amazon-s3-and-cloudfront' ),
				// StorageSettings
				'storage_provider_title'                => _x( 'Storage Provider', 'Section title', 'amazon-s3-and-cloudfront' ),
				'edit_storage_provider'                 => _x( 'Change cloud storage provider or location', 'Edit storage provider button tooltip', 'amazon-s3-and-cloudfront' ),
				'view_provider_console'                 => _x( 'View in provider\'s console', 'Provider console link alt text', 'amazon-s3-and-cloudfront' ),
				// BucketPanel
				'bucket_title'                          => _x( 'Bucket', 'Section title', 'amazon-s3-and-cloudfront' ),
				'change_bucket'                         => _x( 'Change bucket', 'Change link description', 'amazon-s3-and-cloudfront' ),
				'bapa_enabled'                          => __( 'Block All Public Access Enabled', 'amazon-s3-and-cloudfront' ),
				'bapa_enabled_title'                    => __( 'Public access to bucket has been blocked at either account or bucket level.', 'amazon-s3-and-cloudfront' ),
				'bapa_disabled'                         => __( 'Block All Public Access Disabled', 'amazon-s3-and-cloudfront' ),
				'bapa_disabled_title'                   => __( 'Public access to bucket has not been blocked at either account or bucket level.', 'amazon-s3-and-cloudfront' ),
				'bapa_unknown'                          => __( 'Block All Public Access Status Unknown', 'amazon-s3-and-cloudfront' ),
				'bapa_unknown_title'                    => __( 'Public access to bucket status unknown, please grant IAM User the s3:GetBucketPublicAccessBlock permission.', 'amazon-s3-and-cloudfront' ),
				'object_ownership_enforced'             => __( 'Object Ownership Enforced', 'amazon-s3-and-cloudfront' ),
				'object_ownership_enforced_title'       => __( 'Object Ownership has been enforced on the bucket.', 'amazon-s3-and-cloudfront' ),
				'object_ownership_not_enforced'         => __( 'Object Ownership Not Enforced', 'amazon-s3-and-cloudfront' ),
				'object_ownership_not_enforced_title'   => __( 'Object Ownership has not been enforced on the bucket.', 'amazon-s3-and-cloudfront' ),
				'object_ownership_unknown'              => __( 'Object Ownership Status Unknown', 'amazon-s3-and-cloudfront' ),
				'object_ownership_unknown_title'        => __( 'Object Ownership status in the bucket unknown, please grant IAM User the s3:GetBucketPublicAccessBlock permission.', 'amazon-s3-and-cloudfront' ),
				'unknown'                               => _x( 'Unknown', 'Used when region, provider etc is not in reference data', 'amazon-s3-and-cloudfront' ),
				// StorageSettingsPanel
				'storage_settings_title'                => _x( 'Storage Settings', 'Section title', 'amazon-s3-and-cloudfront' ),
				'copy_files_to_bucket'                  => _x( 'Offload Media', 'Setting title', 'amazon-s3-and-cloudfront' ),
				'copy_files_to_bucket_desc'             => _x( 'Copies media files to the storage provider after being uploaded, edited, or optimized.', 'Setting description', 'amazon-s3-and-cloudfront' ) . ' ' . $this->settings_more_info_link( 'copy-to-s3', 'How offloading media works' ),
				'path'                                  => _x( 'Add Prefix to Bucket Path', 'Setting title', 'amazon-s3-and-cloudfront' ),
				'path_desc'                             => _x( 'Groups media from this site together by using a common prefix in the bucket path of offloaded media files.', 'Setting description', 'amazon-s3-and-cloudfront' ) . ' ' . $this->settings_more_info_link( 'object-prefix', 'Why bucket prefixes are useful' ),
				'year_month'                            => _x( 'Add Year & Month to Bucket Path', 'Setting title', 'amazon-s3-and-cloudfront' ),
				'year_month_desc'                       => _x( 'Provides another level of organization within the bucket by including the year & month in which the file was uploaded to the site.', 'Setting description', 'amazon-s3-and-cloudfront' ),
				'object_versioning'                     => _x( 'Add Object Version to Bucket Path', 'Setting title', 'amazon-s3-and-cloudfront' ),
				'object_versioning_desc'                => _x( 'Ensures the latest version of a media item gets delivered by adding a unique timestamp to the bucket path.', 'Setting description', 'amazon-s3-and-cloudfront' ),
				'remove_local_file'                     => _x( 'Remove Local Media', 'Setting title', 'amazon-s3-and-cloudfront' ),
				'remove_local_file_desc'                => _x( 'Frees up storage space by deleting local media files after they have been offloaded.', 'Setting description', 'amazon-s3-and-cloudfront' ),
				'remove_local_file_message'             => $remove_local_msg,
				'lost_files_notice_heading'             => _x( 'Broken URLs', 'warning heading', 'amazon-s3-and-cloudfront' ),
				'lost_files_notice_message'             => __( 'There will be broken URLs for files that don\'t exist locally. You can fix this by enabling <strong>Deliver Offloaded Media</strong> to use the offloaded media.', 'amazon-s3-and-cloudfront' ),
				// DeliverySettings
				'delivery_provider_title'               => _x( 'Delivery Provider', 'Section title', 'amazon-s3-and-cloudfront' ),
				'edit_delivery_provider'                => _x( 'Change delivery provider', 'Edit delivery provider button tooltip', 'amazon-s3-and-cloudfront' ),
				// DeliverySettingsPanel
				'delivery_settings_title'               => _x( 'Delivery Settings', 'Section title', 'amazon-s3-and-cloudfront' ),
				'rewrite_media_urls'                    => _x( 'Deliver Offloaded Media', 'Setting title', 'amazon-s3-and-cloudfront' ),
				'delivery_domain'                       => _x( 'Use Custom Domain Name (CNAME)', 'Setting title', 'amazon-s3-and-cloudfront' ),
				'domain_blank'                          => __( 'Domain cannot be blank.', 'amazon-s3-and-cloudfront' ),
				'domain_invalid_content'                => __( 'Domain can only contain letters, numbers, hyphens (-), and periods (.)', 'amazon-s3-and-cloudfront' ),
				'domain_too_short'                      => __( 'Domain too short.', 'amazon-s3-and-cloudfront' ),
				'force_https'                           => _x( 'Force HTTPS', 'Setting title', 'amazon-s3-and-cloudfront' ),
				'force_https_desc'                      => _x( 'Uses HTTPS for every offloaded media item instead of using the scheme of the current page.', 'Setting description', 'amazon-s3-and-cloudfront' ),
				// Settings notices
				'check_again_title'                     => _x( 'Check again', 'Check again button title', 'amazon-s3-and-cloudfront' ),
				'check_again_active'                    => _x( 'Checking…', 'Check again button title while checking ', 'amazon-s3-and-cloudfront' ),
				'check_again_desc'                      => _x( 'Check settings again, may be resource intensive.', 'Check again button description ', 'amazon-s3-and-cloudfront' ),
				// StoragePage
				'storage_title'                         => _x( 'Storage', 'Page title', 'amazon-s3-and-cloudfront' ),
				'storage_provider_tab_title'            => _x( 'Storage Provider', 'Tab title', 'amazon-s3-and-cloudfront' ),
				'bucket_tab_title'                      => _x( 'Bucket', 'Tab title', 'amazon-s3-and-cloudfront' ),
				'security_tab_title'                    => _x( 'Security', 'Tab title', 'amazon-s3-and-cloudfront' ),
				'copy_files_tab_title'                  => _x( 'Copy Files', 'Tab title', 'amazon-s3-and-cloudfront' ),
				// StorageProviderSubPage
				'select_storage_provider_title'         => _x( '1. Select Provider', 'Section title', 'amazon-s3-and-cloudfront' ),
				'select_auth_method_title'              => _x( '2. Connection Method', 'Section title', 'amazon-s3-and-cloudfront' ),
				'auth_method_title'                     => array(
					'define'      => _x( '3. Add Credentials', 'Section title', 'amazon-s3-and-cloudfront' ),
					'server-role' => _x( '3. Save Setting', 'Section title', 'amazon-s3-and-cloudfront' ),
					'database'    => _x( '3. Add Credentials', 'Section title', 'amazon-s3-and-cloudfront' ),
				),
				'define_access_keys'                    => __( 'Define access keys in wp-config.php', 'amazon-s3-and-cloudfront' ),
				'define_key_file_path'                  => __( 'Define key file path in wp-config.php', 'amazon-s3-and-cloudfront' ),
				'store_access_keys_in_db'               => __( 'I understand the risks but I\'d like to store access keys in the database anyway (not recommended)', 'amazon-s3-and-cloudfront' ),
				'access_key_id'                         => _x( 'Access Key ID', 'Setting title', 'amazon-s3-and-cloudfront' ),
				'secret_access_key'                     => _x( 'Secret Access Key', 'Setting title', 'amazon-s3-and-cloudfront' ),
				'store_key_file_in_db'                  => __( 'I understand the risks but I\'d like to store the key file\'s contents in the database anyway (not recommended)', 'amazon-s3-and-cloudfront' ),
				'key_file'                              => _x( 'Key File', 'Setting title', 'amazon-s3-and-cloudfront' ),
				// BucketSettingsSubPage
				'bucket_source_title'                   => _x( '1. New or Existing Bucket?', 'Section title', 'amazon-s3-and-cloudfront' ),
				'use_existing_bucket'                   => _x( 'Use Existing Bucket', 'Option title', 'amazon-s3-and-cloudfront' ),
				'create_new_bucket'                     => _x( 'Create New Bucket', 'Option title', 'amazon-s3-and-cloudfront' ),
				'existing_bucket_title'                 => _x( '2. Select Bucket', 'Section title', 'amazon-s3-and-cloudfront' ),
				'new_bucket_title'                      => _x( '2. Bucket Details', 'Section title', 'amazon-s3-and-cloudfront' ),
				'enter_bucket'                          => _x( 'Enter bucket name', 'Option title', 'amazon-s3-and-cloudfront' ),
				'select_bucket'                         => _x( 'Browse existing buckets', 'Option title', 'amazon-s3-and-cloudfront' ),
				'bucket_name'                           => _x( 'Bucket Name', 'Setting title', 'amazon-s3-and-cloudfront' ),
				'bucket_icon'                           => _x( 'Bucket icon', 'Bucket icon alt text', 'amazon-s3-and-cloudfront' ),
				'region'                                => _x( 'Region', 'Setting title', 'amazon-s3-and-cloudfront' ),
				'enter_bucket_name_placeholder'         => _x( 'Enter bucket name…', 'Placeholder', 'amazon-s3-and-cloudfront' ),
				'save_enter_bucket'                     => _x( 'Save Bucket Settings', 'Button text', 'amazon-s3-and-cloudfront' ),
				'save_select_bucket'                    => _x( 'Save Selected Bucket', 'Button text', 'amazon-s3-and-cloudfront' ),
				'save_new_bucket'                       => _x( 'Create New Bucket', 'Button text', 'amazon-s3-and-cloudfront' ),
				// SecuritySubPage
				'block_public_access_title'             => _x( 'Block All Public Access', 'Section title', 'amazon-s3-and-cloudfront' ),
				'block_public_access_disabled_sub'      => __( 'Block All Public Access is currently <strong>disabled</strong>', 'amazon-s3-and-cloudfront' ),
				'block_public_access_enabled_sub'       => __( 'Block All Public Access is currently <strong>enabled</strong>', 'amazon-s3-and-cloudfront' ),
				'block_public_access_enabled_setup_sub' => __( '<strong>Warning:</strong> Block All Public Access is currently <strong>enabled</strong>', 'amazon-s3-and-cloudfront' ),
				'object_ownership_title'                => _x( 'Object Ownership', 'Section title', 'amazon-s3-and-cloudfront' ),
				'object_ownership_not_enforced_sub'     => __( 'Object Ownership is currently <strong>not enforced</strong>', 'amazon-s3-and-cloudfront' ),
				'object_ownership_enforced_sub'         => __( 'Object Ownership is currently <strong>enforced</strong>', 'amazon-s3-and-cloudfront' ),
				'object_ownership_enforced_setup_sub'   => __( '<strong>Warning:</strong> Object Ownership is currently <strong>enforced</strong>', 'amazon-s3-and-cloudfront' ),
				'update_bucket_security'                => _x( 'Update Bucket Security', 'Button text', 'amazon-s3-and-cloudfront' ),
				'keep_bucket_security'                  => _x( 'Keep Bucket Security As Is', 'Button text', 'amazon-s3-and-cloudfront' ),
				// DeliveryPage
				'delivery_title'                        => _x( 'Delivery', 'Page title', 'amazon-s3-and-cloudfront' ),
				'select_delivery_provider_title'        => _x( '1. Select Delivery Provider', 'Section title', 'amazon-s3-and-cloudfront' ),
				'enter_other_cdn_name_title'            => _x( '2. Use Another CDN', 'Section title', 'amazon-s3-and-cloudfront' ),
				'enter_other_cdn_name_placeholder'      => _x( 'Enter CDN name…', 'Placeholder', 'amazon-s3-and-cloudfront' ),
				'quick_start_guide'                     => __( 'Quick Start Guide', 'amazon-s3-and-cloudfront' ),
				'view_quick_start_guide'                => _x( 'View quick start guide', 'Help icon tooltip', 'amazon-s3-and-cloudfront' ),
				'save_delivery_provider'                => _x( 'Save Delivery Provider', 'Button text', 'amazon-s3-and-cloudfront' ),
				'nothing_to_save'                       => __( 'No changes to save', 'amazon-s3-and-cloudfront' ),
				'no_delivery_provider_name'             => __( 'A CDN name has not been entered.', 'amazon-s3-and-cloudfront' ),
				'delivery_provider_name_short'          => __( 'CDN name too short.', 'amazon-s3-and-cloudfront' ),
				// AssetsPage
				'assets_title'                          => _x( 'Asset Settings', 'Page title', 'amazon-s3-and-cloudfront' ),
				'assets_upsell_heading'                 => __( 'Media Files Are Only the Beginning…', 'amazon-s3-and-cloudfront' ),
				'assets_upsell_description'             => sprintf(
					__( 'Assets such as scripts, styles, and fonts can also be served from a Content Delivery Network (CDN) to improve website load times. <a href="%s">Upgrade to a qualifying license of WP Offload Media</a> to speed up the delivery of these critical assets today.', 'amazon-s3-and-cloudfront' ),
					$this->dbrains_url( '/wp-offload-media/pricing/', array( 'utm_campaign' => 'WP+Offload+S3', 'utm_content' => 'upsell_assets' ) )
				),
				'assets_uppsell_benefits'               => array(
					'css'   => _x( 'Cascading style sheets (CSS)', 'Assets uppsell benefit', 'amazon-s3-and-cloudfront' ),
					'js'    => _x( 'JavaScript (JS)', 'Assets uppsell benefit', 'amazon-s3-and-cloudfront' ),
					'fonts' => _x( 'Fonts', 'Assets uppsell benefit', 'amazon-s3-and-cloudfront' ),
				),
				'assets_upsell_cta'                     => _x( 'Upgrade now', 'Upsell call to action', 'amazon-s3-and-cloudfront' ),
				'assets_upsell_cta_note'                => __( 'Already have a qualifying license? <a href="#/license">Enter License Key</a>', 'amazon-s3-and-cloudfront' ),

				// ToolsPage
				'tools_title'                           => _x( 'Bulk Management Tools', 'Page title', 'amazon-s3-and-cloudfront' ),
				'tools_upsell_heading'                  => __( 'Easily Manage Local and Offloaded Media', 'amazon-s3-and-cloudfront' ),
				'tools_upsell_description'              => sprintf(
					__( 'Whether you need to offload a library of existing media items or return offloaded files back to your local server, there\'s a tool for every job. <a href="%s">Upgrade to any license of WP Offload Media</a> to take advantage of these powerful tools today.', 'amazon-s3-and-cloudfront' ),
					$this->dbrains_url( '/wp-offload-media/pricing/', array( 'utm_campaign' => 'WP+Offload+S3', 'utm_content' => 'upsell_tools' ) )
				),
				'tools_uppsell_benefits'                => array(
					'offload'       => _x( 'Offload remaining media', 'Assets uppsell benefit', 'amazon-s3-and-cloudfront' ),
					'download'      => _x( 'Download files from bucket to server', 'Assets uppsell benefit', 'amazon-s3-and-cloudfront' ),
					'remove_bucket' => _x( 'Remove all files from bucket', 'amazon-s3-and-cloudfront' ),
					'remove_server' => _x( 'Remove all files from server', 'amazon-s3-and-cloudfront' ),
				),
				'tools_upsell_cta'                      => _x( 'Upgrade now', 'Upsell call to action', 'amazon-s3-and-cloudfront' ),

				// SupportPage
				'no_support'                            => __( 'As this is a free plugin, we do not provide support.', 'amazon-s3-and-cloudfront' ),
				'community_support'                     => sprintf(
					__( 'You may ask the WordPress community for help by posting to the <a href="%s">WordPress.org support forum</a>. Response time can range from a few days to a few weeks and will likely be from a non-developer.', 'amazon-s3-and-cloudfront' ),
					'https://wordpress.org/plugins/amazon-s3-and-cloudfront/'
				),
				'upgrade_for_support'                   => sprintf(
					__( 'If you want a <strong>timely response via email from a developer</strong> who works on this plugin, <a href="%s">upgrade</a> and send us an email.', 'amazon-s3-and-cloudfront' ),
					$this->dbrains_url( '/wp-offload-media/', array( 'utm_campaign' => 'WP+Offload+S3', 'utm_content' => 'support+tab' ) )
				),
				'report_a_bug'                          => sprintf(
					__( 'If you\'ve found a bug, please <a href="%s">submit an issue on GitHub</a>.', 'amazon-s3-and-cloudfront' ),
					'https://github.com/deliciousbrains/wp-amazon-s3-and-cloudfront/issues'
				),
				'diagnostic_info_title'                 => _x( 'Diagnostic Info', 'Section title', 'amazon-s3-and-cloudfront' ),
				'download_diagnostics'                  => _x( 'Download', 'Download diagnostics button text', 'amazon-s3-and-cloudfront' ),
				// Mimic WP Core's notice text, therefore no translation needed here.
				'settings_saved'                        => __( 'Settings saved.' ),
				'dismiss_notice'                        => __( 'Dismiss this notice.' ),
			),
			'settings'                         => $this->obfuscate_sensitive_settings( $this->get_all_settings() ),
			'defined_settings'                 => array_keys( $this->get_defined_settings() ),
			'default_storage_provider'         => static::get_default_storage_provider(), // Hoisted up as needed before providers derived
			'storage_providers'                => $this->get_available_storage_provider_details(),
			'default_delivery_provider'        => static::get_default_delivery_provider(),
			'delivery_providers'               => $this->get_available_delivery_provider_details(),
			'nonce'                            => wp_create_nonce( 'wp_rest' ),
			'urls'                             => $this->get_js_urls(),
			'docs'                             => $this->get_docs(),
			'endpoints'                        => $this->get_api_manager()->api_endpoints(),
			'title'                            => $this->get_plugin_page_title(),
			'diagnostics'                      => $this->output_diagnostic_info(),
			'counts'                           => $this->media_counts(),
			'summary_counts'                   => $this->get_summary_counts(),
			'offload_remaining_upsell'         => $this->get_offload_remaining_upsell_message(),
			'notifications'                    => $this->get_notifications( '', true ),
			'upgrades'                         => $this->get_upgrades_info(),
			'is_plugin_setup'                  => $this->is_plugin_setup(),
			'is_plugin_setup_with_credentials' => $this->is_plugin_setup( true ),
			'needs_access_keys'                => $this->get_storage_provider()->needs_access_keys(),
			'bucket_writable'                  => $this->bucket_writable(),
			'settings_validation'              => $this->settings_validation_status(),
		);

		// Where the magic happens.
		$this->load_settings_assets( $config );

		$this->http_prepare_download_log();
		$this->check_for_gd_imagick();
		$this->check_for_items_table();
		$this->init_admin_footer();

		do_action( 'as3cf_plugin_load' );
	}

	/**
	 * Returns keyed array of all settings values regardless of whether explicitly set or not.
	 *
	 * @param bool $pseudo Include pseudo settings that are derived rather than saved?
	 *
	 * @return array
	 */
	public function get_all_settings( bool $pseudo = true ): array {
		$settings = parent::get_all_settings( $pseudo );

		/*
		 * Pseudo (dynamic) settings.
		 */
		if ( ! $pseudo ) {
			return $settings;
		}

		$settings['block-public-access-supported'] = $this->get_storage_provider()->block_public_access_supported();
		$settings['object-ownership-supported']    = $this->get_storage_provider()->object_ownership_supported();

		// TODO: Use transient for storing this ephemeral data, and ensure it is updated, API.
		$settings['block-public-access']       = null;
		$settings['object-ownership-enforced'] = null;

		// Without credentials, bucket or region, we can't get bucket access settings,
		// the only remaining pseudo settings.
		if (
			$this->get_storage_provider()->needs_access_keys() ||
			empty( $settings['bucket'] ) ||
			empty( $settings['region'] ) ||
			is_wp_error( $settings['region'] )
		) {
			return $settings;
		}

		try {
			$provider_client = $this->get_provider_client( $settings['region'] );
		} catch ( Exception $e ) {
			$provider_client = null;
			AS3CF_Error::log( $e->getMessage() );
		}

		if (
			! empty( $provider_client ) &&
			$settings['block-public-access-supported'] &&
			is_subclass_of( $provider_client, 'DeliciousBrains\WP_Offload_Media\Providers\Storage\Storage_Provider' )
		) {
			try {
				$settings['block-public-access'] = $provider_client->public_access_blocked( $settings['bucket'] );
			} catch ( Exception $e ) {
				$settings['block-public-access'] = null;
				AS3CF_Error::log( $e->getMessage() );
			}
		}

		if (
			! empty( $provider_client ) &&
			$settings['object-ownership-supported'] &&
			is_subclass_of( $provider_client, 'DeliciousBrains\WP_Offload_Media\Providers\Storage\Storage_Provider' )
		) {
			try {
				$settings['object-ownership-enforced'] = $provider_client->object_ownership_enforced( $settings['bucket'] );
			} catch ( Exception $e ) {
				$settings['object-ownership-enforced'] = null;
				AS3CF_Error::log( $e->getMessage() );
			}
		}

		return $settings;
	}

	/**
	 * Allowed settings keys for this plugin.
	 *
	 * @param bool $include_legacy Should legacy keys be included? Optional, default false.
	 *
	 * @return array
	 */
	public function get_allowed_settings_keys( bool $include_legacy = false ): array {
		$keys = array(
			// Storage
			'provider',
			'access-key-id',
			'secret-access-key',
			'key-file-path',
			'key-file',
			'use-server-roles',
			'bucket',
			'region',
			'use-bucket-acls',
			'enable-object-prefix',
			'object-prefix',
			'use-yearmonth-folders',
			'object-versioning',
			'copy-to-s3', // TODO: Rename
			// Delivery
			'delivery-provider',
			'delivery-provider-service-name',
			'enable-delivery-domain',
			'delivery-domain',
			'enable-signed-urls',
			'signed-urls-key-id',
			'signed-urls-key-file-path',
			'signed-urls-object-prefix',
			'force-https',
			'serve-from-s3', // TODO: Rename
			// Advanced
			'remove-local-file',
		);

		if ( $include_legacy ) {
			$keys = array_merge( $keys, $this->get_legacy_settings_keys() );
		}

		return $keys;
	}

	/**
	 * Legacy settings that used to be allowed and could still be in defines.
	 *
	 * @return array
	 */
	private function get_legacy_settings_keys(): array {
		return array(
			'virtual-host', // Legacy
			'domain', // Legacy
			'cloudfront', // Legacy
		);
	}

	/**
	 * Add or overwrite new style defined values with legacy value.
	 *
	 * @param array $defines
	 *
	 * @return array
	 */
	protected function get_legacy_defined_settings( array $defines ): array {
		if ( defined( 'AS3CF_PROVIDER' ) ) {
			$defines['provider'] = AS3CF_PROVIDER;
		}

		if ( defined( 'AS3CF_REGION' ) ) {
			$defines['region'] = AS3CF_REGION;
		}

		if ( defined( 'AS3CF_BUCKET' ) ) {
			$defines['bucket'] = AS3CF_BUCKET;
		}

		// Depending on configured storage provider, there may be more "legacy" defines that can override settings.
		$storage_provider = $this->get_storage_provider();

		// If the storage provider has not been set yet, try and get from defines,
		// or fall back to default storage provider if not defined.
		// If credentials are being defined for a provider other than the default (AWS),
		// it is required that the provider be set via define too.
		if ( empty( $storage_provider ) ) {
			$provider = empty( $defines['provider'] ) ? static::get_default_storage_provider() : $defines['provider'];

			// To avoid infinite recursion, we can only try this for known storage provider keys.
			if ( in_array( $provider, array_keys( static::$storage_provider_classes ) ) ) {
				$this->set_storage_provider( $provider );
				$storage_provider = $this->get_storage_provider();
			}
		}

		if ( ! empty( $storage_provider ) ) {
			if ( $storage_provider->use_access_keys_allowed() ) {
				if ( $storage_provider->access_key_id_constant() ) {
					$defines['access-key-id'] = $storage_provider->get_access_key_id();
				}

				if ( $storage_provider->secret_access_key_constant() ) {
					$defines['secret-access-key'] = $storage_provider->get_secret_access_key();
				}
			}

			if (
				$storage_provider->use_server_roles_allowed() &&
				$storage_provider->is_use_server_roles_constant_defined()
			) {
				$defines['use-server-roles'] = $storage_provider->use_server_roles();
			}

			if (
				$storage_provider->use_key_file_allowed() &&
				$storage_provider->is_key_file_path_constant_defined()
			) {
				$defines['key-file-path'] = $storage_provider->get_key_file_path();
			}
		}

		return $defines;
	}

	/**
	 * Get the settings that should not be shown once saved.
	 *
	 * @return array
	 */
	public function get_sensitive_settings(): array {
		return array(
			'secret-access-key',
		);
	}

	/**
	 * Get the blacklisted settings for monitoring changes in defines.
	 * These settings will not be saved in the database.
	 *
	 * @return array
	 */
	public function get_monitored_settings_blacklist(): array {
		return array(
			'access-key-id',
			'secret-access-key',
		);
	}

	/**
	 * List of settings that should skip full sanitize.
	 *
	 * @return array
	 */
	public function get_skip_sanitize_settings(): array {
		return array( 'key-file' );
	}

	/**
	 * @inheritDoc
	 */
	public function get_path_format_settings(): array {
		return array(
			'key-file-path',
			'signed-urls-key-file-path',
		);
	}

	/**
	 * @inheritDoc
	 */
	public function get_prefix_format_settings(): array {
		return array(
			'object-prefix',
			'signed-urls-object-prefix',
		);
	}

	/**
	 * List of settings that should be treated as booleans.
	 *
	 * @return array
	 */
	public function get_boolean_format_settings(): array {
		return array(
			// Storage
			'use-server-roles',
			'use-bucket-acls',
			'enable-object-prefix',
			'use-yearmonth-folders',
			'object-versioning',
			'copy-to-s3', // TODO: Rename
			// Delivery
			'enable-delivery-domain',
			'enable-signed-urls',
			'force-https',
			'serve-from-s3', // TODO: Rename
			// Advanced
			'remove-local-file',
		);
	}

	/**
	 * Display the main settings page for the plugin
	 */
	function render_page() {
		do_action( 'as3cf_pre_settings_render' );

		$this->render_view( 'settings' );

		do_action( 'as3cf_post_settings_render' );
	}

	/**
	 * Get the tabs available for the plugin settings page
	 *
	 * @return array
	 */
	function get_settings_tabs() {
		$tabs = array(
			'media'   => _x( 'Media Library', 'Show the media library tab', 'amazon-s3-and-cloudfront' ),
			'addons'  => _x( 'Addons', 'Show the addons tab', 'amazon-s3-and-cloudfront' ),
			'support' => _x( 'Support', 'Show the support tab', 'amazon-s3-and-cloudfront' ),
		);

		return apply_filters( 'as3cf_settings_tabs', $tabs );
	}

	/**
	 * Recursively build addons list
	 *
	 * @param array|null $addons
	 */
	function render_addons( $addons = null ) {
		if ( is_null( $addons ) ) {
			$addons = $this->get_addons();
		}

		foreach ( $addons as $slug => $addon ) {
			$this->render_view( 'addon', array(
				'slug'  => $slug,
				'addon' => $addon,
			) );
		}
	}

	/**
	 * Get the prefix path for the files. Ignores WP media library
	 * year month subdirectory setting and just uses S3 setting
	 *
	 * @param string $time
	 * @param bool   $can_use_yearmonth
	 *
	 * @return string
	 */
	public function get_dynamic_prefix( $time = null, $can_use_yearmonth = true ) {
		$prefix = '';
		$subdir = '';

		// If multisite (and if not the main site in a post-MU network)
		if ( is_multisite() && ! ( is_main_network() && is_main_site() && defined( 'MULTISITE' ) ) ) {
			if ( ! get_site_option( 'ms_files_rewriting' ) ) {
				/*
				 * If ms-files rewriting is disabled (networks created post-3.5), it is fairly
				 * straightforward: Append sites/%d if we're not on the main site (for post-MU
				 * networks). (The extra directory prevents a four-digit ID from conflicting with
				 * a year-based directory for the main site. But if a MU-era network has disabled
				 * ms-files rewriting manually, they don't need the extra directory, as they never
				 * had wp-content/uploads for the main site.)
				 */

				if ( defined( 'MULTISITE' ) ) {
					$prefix = '/sites/' . get_current_blog_id();
				} else {
					$prefix = '/' . get_current_blog_id();
				}
			} elseif ( defined( 'UPLOADS' ) && ! ms_is_switched() ) {
				/*
				 * Handle the old-form ms-files.php rewriting if the network still has that enabled.
				 * When ms-files rewriting is enabled, then we only listen to UPLOADS when:
				 * 1) We are not on the main site in a post-MU network, as wp-content/uploads is used
				 *    there, and
				 * 2) We are not switched, as ms_upload_constants() hardcodes these constants to reflect
				 *    the original blog ID.
				 *
				 * Rather than UPLOADS, we actually use BLOGUPLOADDIR if it is set, as it is absolute.
				 * (And it will be set, see ms_upload_constants().) Otherwise, UPLOADS can be used, as
				 * as it is relative to ABSPATH. For the final piece: when UPLOADS is used with ms-files
				 * rewriting in multisite, the resulting URL is /files. (#WP22702 for background.)
				 */
				if ( defined( 'BLOGUPLOADDIR' ) ) {
					$prefix = untrailingslashit( BLOGUPLOADDIR );
				} else {
					$prefix = ABSPATH . UPLOADS;
				}
			}
		}

		if ( $this->get_setting( 'use-yearmonth-folders' ) && $can_use_yearmonth ) {
			$subdir = $this->get_year_month_directory_name( $time );
			$prefix .= $subdir;
		}

		// support legacy MS installs (<3.5 since upgraded) for subsites
		if ( is_multisite() && ! ( is_main_network() && is_main_site() ) && false === strpos( $prefix, 'sites/' ) ) {
			$details          = get_blog_details( get_current_blog_id() );
			$legacy_ms_prefix = 'sites/' . $details->blog_id . '/';
			$legacy_ms_prefix = apply_filters( 'as3cf_legacy_ms_subsite_prefix', $legacy_ms_prefix, $details );
			$prefix           = '/' . trailingslashit( ltrim( $legacy_ms_prefix, '/' ) ) . ltrim( $subdir, '/' );
		}

		return $prefix;
	}

	/**
	 * Generate the year and month sub-directory from $time if provided,
	 * then POST time if available, otherwise use current time
	 *
	 * @param string $time
	 *
	 * @return string
	 */
	function get_year_month_directory_name( $time = null ) {
		if ( ! $time && isset( $_POST['post_id'] ) ) {
			$time = get_post_field( 'post_date', $_POST['post_id'] );
		}

		if ( ! $time ) {
			$time = current_time( 'mysql' );
		}

		$y      = substr( $time, 0, 4 );
		$m      = substr( $time, 5, 2 );
		$subdir = "/$y/$m";

		if ( false === strpos( $subdir, '//' ) ) {
			return $subdir;
		}

		return '';
	}

	/**
	 * Make admin notice for when object ACL has changed
	 *
	 * @param Media_Library_Item $as3cf_item
	 * @param string|null        $size
	 */
	public function make_acl_admin_notice( Media_Library_Item $as3cf_item, $size = null ) {
		$filename = wp_basename( $as3cf_item->path( $size ) );
		$acl      = $as3cf_item->is_private( $size ) ? $this->get_storage_provider()->get_private_acl() : $this->get_storage_provider()->get_default_acl();
		$acl_name = $this->get_acl_display_name( $acl );
		$text     = sprintf( __( 'The file %s has been given %s permissions in the bucket.', 'amazon-s3-and-cloudfront' ), "<strong>{$filename}</strong>", "<strong>{$acl_name}</strong>" );

		$this->notices->add_notice( $text );
	}

	/**
	 * Check if PHP GD and Imagick is installed
	 */
	function check_for_gd_imagick() {
		if ( ! $this->is_plugin_setup( true ) ) {
			// No notice until plugin is setup
			return;
		}

		$gd_enabled      = $this->gd_enabled();
		$imagick_enabled = $this->imagick_enabled();

		if ( ! $gd_enabled && ! $imagick_enabled ) {
			$this->notices->add_notice(
				__( '<strong>WP Offload Media Requirement Missing</strong> &mdash; Looks like you don\'t have an image manipulation library installed on this server and configured with PHP. You may run into trouble if you try to edit images. Please setup GD or ImageMagick.', 'amazon-s3-and-cloudfront' ),
				array( 'flash' => false, 'only_show_to_user' => false, 'only_show_in_settings' => true )
			);
		}
	}

	/**
	 * Ensure items table(s) exists in the database
	 */
	private function check_for_items_table() {
		if ( ! $this->is_plugin_setup( true ) ) {
			// No notice until plugin is setup
			return;
		}

		if ( is_multisite() && ! is_network_admin() ) {
			return;
		}

		$missing_tables = $this->get_db_init_status( false );

		if ( count( $missing_tables ) !== 0 ) {
			$this->notices->add_notice(
				sprintf(
					__( '<strong>Missing Table</strong> &mdash; One or more required database tables are missing, please check the Diagnostic Info in the Support tab for details. %s', 'amazon-s3-and-cloudfront' ),
					static::more_info_link(
						'/wp-offload-media/doc/missing-table-error-notice',
						'missing-table'
					)
				),
				array(
					'custom_id'             => 'items_table_error',
					'type'                  => 'error',
					'dismissible'           => false,
					'flash'                 => false,
					'only_show_to_user'     => false,
					'only_show_in_settings' => true,
				)
			);
		} else {
			$this->notices->remove_notice_by_id( 'items_table_error' );
		}
	}

	/**
	 * Output image size names and dimensions to a string
	 *
	 * @return string
	 */
	function get_image_sizes_details() {
		global $_wp_additional_image_sizes;

		$size_details                 = '';
		$get_intermediate_image_sizes = get_intermediate_image_sizes();

		// Create array with sizes
		foreach ( $get_intermediate_image_sizes as $size ) {
			if ( in_array( $size, array( 'thumb', 'thumbnail', 'medium', 'large', 'post-thumbnail' ) ) ) {
				// Run checks for dimension and name values
				if ( ( $width = get_option( $size . '_size_w' ) ) && ( $height = get_option( $size . '_size_h' ) ) ) {
					$size_details .= $size . ' (' . $width . 'x' . $height . ')' . "\r\n";
				} else {
					$size_details .= $size . ' (none)' . "\r\n";
				}
			} elseif ( isset( $_wp_additional_image_sizes[ $size ] ) ) {
				$size_details .= $size . ' (' . $_wp_additional_image_sizes[ $size ]['width'] . 'x' . $_wp_additional_image_sizes[ $size ]['height'] . ')' . "\r\n";
			}
		}

		return $size_details;
	}

	/**
	 * Diagnostic information for the support tab
	 *
	 * @return string
	 */
	public function output_diagnostic_info(): string {
		global $table_prefix;
		global $wpdb;

		/*
		 * WordPress & Server Environment
		 */

		$output = 'site_url(): ';
		$output .= esc_html( site_url() );
		$output .= "\r\n";

		$output .= 'home_url(): ';
		$output .= esc_html( home_url() );
		$output .= "\r\n";

		$output .= 'Database Name: ';
		$output .= esc_html( $wpdb->dbname );
		$output .= "\r\n";

		$output .= 'Table Prefix: ';
		$output .= esc_html( $table_prefix );
		$output .= "\r\n";

		$output .= 'WordPress: ';
		$output .= get_bloginfo( 'version', 'display' );
		if ( is_multisite() ) {
			$output .= ' Multisite ';
			$output .= '(' . ( is_subdomain_install() ? 'subdomain' : 'subdirectory' ) . ')';
			$output .= "\r\n";
			$output .= 'Multisite Site Count: ';
			$output .= esc_html( get_blog_count() );
			$output .= "\r\n";
			$output .= 'Domain Mapping: ' . ( defined( 'SUNRISE' ) && SUNRISE ? 'Enabled' : 'Disabled' );
		}
		$output .= "\r\n";

		$output .= 'Web Server: ';
		$output .= esc_html( ! empty( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '' );
		$output .= "\r\n";

		$output .= 'PHP: ';
		if ( function_exists( 'phpversion' ) ) {
			$output .= esc_html( phpversion() );
		}
		$output .= "\r\n";

		$output .= 'MySQL: ';
		$output .= esc_html( $wpdb->db_version() );
		$output .= "\r\n";

		$output .= 'ext/mysqli: ';
		$output .= empty( $wpdb->use_mysqli ) ? 'no' : 'yes';
		$output .= "\r\n";

		$output .= 'PHP Memory Limit: ';
		if ( function_exists( 'ini_get' ) ) {
			$output .= esc_html( ini_get( 'memory_limit' ) );
		}
		$output .= "\r\n";

		$output .= 'WP Memory Limit: ';
		$output .= esc_html( WP_MEMORY_LIMIT );
		$output .= "\r\n";

		$output .= 'Memory Usage: ';
		$output .= size_format( memory_get_usage( true ) );
		$output .= "\r\n";

		$output .= 'Blocked External HTTP Requests: ';
		if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL ) {
			$output .= 'None';
		} else {
			$accessible_hosts = ( defined( 'WP_ACCESSIBLE_HOSTS' ) ) ? WP_ACCESSIBLE_HOSTS : '';

			if ( empty( $accessible_hosts ) ) {
				$output .= 'ALL';
			} else {
				$output .= 'Partially (Accessible Hosts: ' . esc_html( $accessible_hosts ) . ')';
			}
		}
		$output .= "\r\n";

		$output .= 'WP Locale: ';
		$output .= esc_html( get_locale() );
		$output .= "\r\n";

		$output .= 'Organize offloads by month/year: ';
		$output .= esc_html( get_option( 'uploads_use_yearmonth_folders' ) ? 'Enabled' : 'Disabled' );
		$output .= "\r\n";

		$output .= 'WP_DEBUG: ';
		$output .= esc_html( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 'Yes' : 'No' );
		$output .= "\r\n";

		$output .= 'WP_DEBUG_LOG: ';
		$output .= esc_html( ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ? 'Yes' : 'No' );
		$output .= "\r\n";

		$output .= 'WP_DEBUG_DISPLAY: ';
		$output .= esc_html( ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) ? 'Yes' : 'No' );
		$output .= "\r\n";

		$output .= 'SCRIPT_DEBUG: ';
		$output .= esc_html( ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? 'Yes' : 'No' );
		$output .= "\r\n";

		$output .= 'WP Max Upload Size: ';
		$output .= esc_html( size_format( wp_max_upload_size() ) );
		$output .= "\r\n";

		$output .= 'PHP Time Limit: ';
		if ( function_exists( 'ini_get' ) ) {
			$output .= esc_html( ini_get( 'max_execution_time' ) );
		}
		$output .= "\r\n";

		$output .= 'PHP Error Log: ';
		if ( function_exists( 'ini_get' ) ) {
			$output .= esc_html( ini_get( 'error_log' ) );
		}
		$output .= "\r\n";

		$output .= 'WP Cron: ';
		$output .= esc_html( ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? 'Disabled' : 'Enabled' );
		$output .= "\r\n";

		$output .= 'fsockopen: ';
		if ( function_exists( 'fsockopen' ) ) {
			$output .= 'Enabled';
		} else {
			$output .= 'Disabled';
		}
		$output .= "\r\n";

		$output          .= 'allow_url_fopen: ';
		$allow_url_fopen = ini_get( 'allow_url_fopen' );
		if ( empty( $allow_url_fopen ) ) {
			$output .= 'Disabled';
		} else {
			$output .= 'Enabled';
		}
		$output .= "\r\n";

		$output .= 'OpenSSL: ';
		if ( $this->open_ssl_enabled() ) {
			$output .= esc_html( OPENSSL_VERSION_TEXT );
		} else {
			$output .= 'Disabled';
		}
		$output .= "\r\n";

		$output .= 'cURL: ';
		if ( function_exists( 'curl_init' ) ) {
			$curl   = curl_version();
			$output .= esc_html( $curl['version'] );
		} else {
			$output .= 'Disabled';
		}
		$output .= "\r\n";

		$output .= 'Zlib Compression: ';
		if ( function_exists( 'gzcompress' ) ) {
			$output .= 'Enabled';
		} else {
			$output .= 'Disabled';
		}
		$output .= "\r\n";

		$output .= 'PHP GD: ';
		if ( $this->gd_enabled() ) {
			$gd_info = gd_info();
			$output  .= isset( $gd_info['GD Version'] ) ? esc_html( $gd_info['GD Version'] ) : 'Enabled';
		} else {
			$output .= 'Disabled';
		}
		$output .= "\r\n";

		$output .= 'Imagick: ';
		if ( $this->imagick_enabled() ) {
			$output .= 'Enabled';
		} else {
			$output .= 'Disabled';
		}
		$output .= "\r\n";

		$output .= 'Basic Auth: ';
		if ( ! empty( $_SERVER['REMOTE_USER'] ) || ! empty( $_SERVER['PHP_AUTH_USER'] ) || ! empty( $_SERVER['REDIRECT_REMOTE_USER'] ) ) {
			$output .= 'Enabled';
		} else {
			$output .= 'Disabled';
		}
		$output .= "\r\n";

		$output .= 'Proxy: ';
		if ( defined( 'WP_PROXY_HOST' ) || defined( 'WP_PROXY_PORT' ) ) {
			$output .= 'Enabled';
		} else {
			$output .= 'Disabled';
		}
		$output .= "\r\n\r\n";

		/*
		 * Media
		 */

		$media_counts = $this->media_counts();

		$output .= 'Total Media Items: ';
		$output .= number_format_i18n( $media_counts['total'] );
		$output .= "\r\n";

		$output .= 'Total Offloaded Media Items: ';
		$output .= number_format_i18n( $media_counts['offloaded'] );
		$output .= "\r\n";

		$output .= 'Total Not Offloaded Media Items: ';
		$output .= number_format_i18n( $media_counts['not_offloaded'] );
		$output .= "\r\n\r\n";

		if ( ! empty( $media_counts['summaries'] ) ) {
			foreach ( $media_counts['summaries'] as $summary_type => $summary_type_counts ) {
				if ( isset( $summary_type_counts['total'] ) ) {
					$summary_type_name = $this->get_summary_type_name( $summary_type );

					$output .= $summary_type_name . ' Items: ';
					$output .= number_format_i18n( $summary_type_counts['total'] );
					$output .= "\r\n";

					$output .= 'Offloaded ' . $summary_type_name . ' Items: ';
					$output .= number_format_i18n( $summary_type_counts['offloaded'] );
					$output .= "\r\n";

					$output .= 'Not Offloaded ' . $summary_type_name . ' Items: ';
					$output .= number_format_i18n( $summary_type_counts['not_offloaded'] );
					$output .= "\r\n\r\n";
				}
			}
		}

		$output .= 'Number of Image Sizes: ';
		$sizes  = count( get_intermediate_image_sizes() );
		$output .= number_format_i18n( $sizes );
		$output .= "\r\n\r\n";

		$output       .= 'Names and Dimensions of Image Sizes: ';
		$output       .= "\r\n";
		$size_details = $this->get_image_sizes_details();
		$output       .= $size_details;
		$output       .= "\r\n";

		/*
		 * Defines
		 */

		$output .= 'WP_CONTENT_DIR: ';
		$output .= esc_html( ( defined( 'WP_CONTENT_DIR' ) ) ? WP_CONTENT_DIR : 'Not defined' );
		$output .= "\r\n";

		$output .= 'WP_CONTENT_URL: ';
		$output .= esc_html( ( defined( 'WP_CONTENT_URL' ) ) ? WP_CONTENT_URL : 'Not defined' );
		$output .= "\r\n";

		$output .= 'UPLOADS: ';
		$output .= esc_html( ( defined( 'UPLOADS' ) ) ? UPLOADS : 'Not defined' );
		$output .= "\r\n";

		$output .= 'WP_PLUGIN_DIR: ';
		$output .= esc_html( ( defined( 'WP_PLUGIN_DIR' ) ) ? WP_PLUGIN_DIR : 'Not defined' );
		$output .= "\r\n";

		$output .= 'WP_PLUGIN_URL: ';
		$output .= esc_html( ( defined( 'WP_PLUGIN_URL' ) ) ? WP_PLUGIN_URL : 'Not defined' );
		$output .= "\r\n\r\n";

		$output .= 'AS3CF_PROVIDER: ';
		$output .= esc_html( ( defined( 'AS3CF_PROVIDER' ) ) ? AS3CF_PROVIDER : 'Not defined' );
		$output .= "\r\n";

		$output .= 'AS3CF_BUCKET: ';
		$output .= esc_html( ( defined( 'AS3CF_BUCKET' ) ) ? AS3CF_BUCKET : 'Not defined' );
		$output .= "\r\n";

		$output .= 'AS3CF_REGION: ';
		$output .= esc_html( ( defined( 'AS3CF_REGION' ) ) ? AS3CF_REGION : 'Not defined' );
		$output .= "\r\n";

		$output .= 'AS3CF_SETTINGS: ';

		if ( ! static::using_legacy_defines() ) {
			$settings_constant = $this::settings_constant();
		}

		if ( ! empty( $settings_constant ) ) {
			$output .= 'Defined';

			if ( 'AS3CF_SETTINGS' !== $settings_constant ) {
				$output .= ' (using ' . $settings_constant . ')';
			}

			$defined_settings_keys = $this->get_non_legacy_defined_settings_keys();
			if ( empty( $defined_settings_keys ) ) {
				$output .= ' - *EMPTY*';
			} else {
				$output .= "\r\n";
				$output .= 'AS3CF_SETTINGS Keys: ' . implode( ', ', $defined_settings_keys );
			}
		} else {
			$output .= 'Not defined';
		}
		$output .= "\r\n\r\n";

		/*
		 * Settings
		 */

		$output .= "Local URL:\r\n";
		$output .= $this->get_local_url_preview();
		$output .= "\r\n";
		$output .= "Offload URL:\r\n";
		$output .= $this->get_url_preview();
		$output .= "\r\n";
		$output .= "\r\n";

		$output .= 'OME Metadata Version: ';
		$output .= esc_html( $this->get_setting( 'post_meta_version' ) );
		$output .= "\r\n\r\n";

		/*
		 * Items db tables status
		 */

		$db_init_statuses = $this->get_db_init_status( true );
		$missing_tables   = $this->get_db_init_status( false );

		$output .= "Custom tables:\r\n";
		if ( count( $missing_tables ) === 0 ) {
			$output .= $db_init_statuses[1]['name'] . ': Ok';
			$output .= "\r\n";
		} else {
			// Output the first 5 missing tables
			$table_count = 0;
			foreach ( $missing_tables as $missing_table ) {
				$table_count++;
				if ( $table_count > 5 ) {
					break;
				}
				$output .= $missing_table['name'] . ': ';
				$output .= $missing_table['status'] ? 'Ok' : 'Missing';
				$output .= "\r\n";
			}
		}
		$output .= "\r\n";

		$storage_provider = $this->get_storage_provider();

		if ( empty( $storage_provider ) ) {
			$output .= 'Storage Provider: Not configured';
			$output .= "\r\n";
		} else {
			$output .= 'Storage Provider: ' . $storage_provider::get_provider_service_name();
			$output .= "\r\n";

			if ( $storage_provider::use_server_roles_allowed() ) {
				$output .= 'Use Server Roles: ';
				$output .= $storage_provider->use_server_roles() ? 'On' : 'Off';
			} else {
				$output .= 'Use Server Roles: N/A';
			}
			$output .= "\r\n";

			if ( $storage_provider::use_key_file_allowed() ) {
				$output .= 'Key File Path: ';
				$output .= empty( $storage_provider->get_key_file_path() ) ? 'None' : esc_html( $storage_provider->get_key_file_path() );
				$output .= "\r\n";
				$output .= 'Key File Path Define: ';
				$output .= $storage_provider::key_file_path_constant() ? $storage_provider::key_file_path_constant() : 'Not defined';
			} else {
				$output .= 'Key File Path: N/A';
			}
			$output .= "\r\n";

			if ( $storage_provider::use_access_keys_allowed() ) {
				$output .= 'Access Keys Set: ';
				$output .= $storage_provider->are_access_keys_set() ? 'Yes' : 'No';
				$output .= "\r\n";
				$output .= 'Access Key ID Define: ';
				$output .= $storage_provider::access_key_id_constant() ? $storage_provider::access_key_id_constant() : 'Not defined';
				$output .= "\r\n";
				$output .= 'Secret Access Key Define: ';
				$output .= $storage_provider::secret_access_key_constant() ? $storage_provider::secret_access_key_constant() : 'Not defined';
			} else {
				$output .= 'Access Keys Set: N/A';
			}
			$output .= "\r\n";
		}
		$output .= "\r\n";

		$bucket = $this->get_setting( 'bucket' );
		$output .= 'Bucket: ';
		$output .= empty( $bucket ) ? '(none)' : esc_html( $bucket );
		$output .= "\r\n";
		$region = '';
		$value  = $this->get_setting( 'region' );
		$output .= 'Region: ';
		if ( is_wp_error( $value ) ) {
			$output .= '(error: "' . esc_html( $value->get_error_message() ) . '")';
		} elseif ( empty( $value ) ) {
			$output .= '(empty)';
		} else {
			$output .= esc_html( $value );
			$region = $value;
		}
		$output .= "\r\n";
		if (
			! empty( $storage_provider ) &&
			! empty( $bucket ) &&
			! empty( $region ) &&
			! $storage_provider->needs_access_keys() &&
			$storage_provider->block_public_access_supported()
		) {
			try {
				$public_access_blocked = $this->get_provider_client( $region )->public_access_blocked( $bucket );
			} catch ( Exception $e ) {
				$public_access_blocked = null;
			}
			$output .= 'Block All Public Access: ';
			if ( true === $public_access_blocked ) {
				$output .= 'Enabled';
			} elseif ( false === $public_access_blocked ) {
				$output .= 'Disabled';
			} else {
				$output .= 'Unknown';
			}
			$output .= "\r\n";
		}
		if (
			! empty( $storage_provider ) &&
			! empty( $bucket ) &&
			! empty( $region ) &&
			! $storage_provider->needs_access_keys() &&
			$storage_provider->object_ownership_supported()
		) {
			try {
				$object_ownership_enforced = $this->get_provider_client( $region )->object_ownership_enforced( $bucket );
			} catch ( Exception $e ) {
				$object_ownership_enforced = null;
			}
			$output .= 'Object Ownership Enforced: ';
			if ( true === $object_ownership_enforced ) {
				$output .= 'Yes';
			} elseif ( false === $object_ownership_enforced ) {
				$output .= 'No';
			} else {
				$output .= 'Unknown';
			}
			$output .= "\r\n";
		}
		$output .= "\r\n";

		$output .= 'Offload Media: ';
		$output .= $this->on_off( 'copy-to-s3' );
		$output .= "\r\n";
		$output .= 'Remove Local Media: ';
		$output .= $this->on_off( 'remove-local-file' );
		$output .= "\r\n";
		$output .= 'Enable Add Prefix to Bucket Path: ';
		$output .= $this->on_off( 'enable-object-prefix' );
		$output .= "\r\n";
		$value  = $this->get_setting( 'object-prefix' );
		$output .= 'Custom Prefix for Bucket Path: ';
		$output .= empty( $value ) ? '(none)' : esc_html( $value );
		$output .= "\r\n";
		$output .= 'Add Year & Month to Bucket Path: ';
		$output .= $this->on_off( 'use-yearmonth-folders' );
		$output .= "\r\n";
		$output .= 'Add Object Version to Bucket Path: ';
		$output .= $this->on_off( 'object-versioning' );
		$output .= "\r\n";
		$output .= "\r\n";

		$delivery_provider = $this->get_delivery_provider();

		if ( empty( $delivery_provider ) ) {
			$output .= 'Delivery Provider: Not configured';
			$output .= "\r\n";
		} else {
			$output .= 'Delivery Provider: ' . $delivery_provider::get_provider_service_name();
			$output .= "\r\n";
			$output .= 'Deliver Offloaded Media: ';
			$output .= $this->on_off( 'serve-from-s3' );
			$output .= "\r\n";

			if ( $delivery_provider::delivery_domain_allowed() ) {
				$output .= 'Use Custom Domain Name (CNAME): ';
				$output .= $this->on_off( 'enable-delivery-domain' );
				$output .= "\r\n";
				$value  = $this->get_setting( 'delivery-domain' );
				$output .= 'Custom Domain (CNAME): ';
				$output .= empty( $value ) ? '(none)' : esc_html( $value );
				$output .= "\r\n";
			}

			if ( $delivery_provider::use_signed_urls_key_file_allowed() ) {
				$output .= 'Serve Private Media: ';
				$output .= $this->on_off( 'enable-signed-urls' );
				$output .= "\r\n";
				$output .= 'Public Key ID Set: ';
				$output .= $delivery_provider->get_signed_urls_key_id() ? 'Yes' : 'No';
				$output .= "\r\n";
				$value  = $this->get_setting( 'signed-urls-key-file-path' );
				$output .= 'Private Key File Path: ';
				$output .= empty( $value ) ? '(none)' : esc_html( $value );
				$output .= "\r\n";
				$value  = $this->get_setting( 'signed-urls-object-prefix' );
				$output .= 'Private Bucket Path: ';
				$output .= empty( $value ) ? '(none)' : esc_html( $value );
				$output .= "\r\n";
			}
			$output .= "\r\n";
		}

		$output .= 'Force HTTPS: ';
		$output .= $this->on_off( 'force-https' );
		$output .= "\r\n";
		$output .= "\r\n";

		$output = apply_filters( 'as3cf_diagnostic_info', $output );
		if ( has_action( 'as3cf_diagnostic_info' ) ) {
			$output .= "\r\n";
		}

		$theme_info = wp_get_theme();

		if ( ! empty( $theme_info ) && is_a( $theme_info, 'WP_Theme' ) ) {
			$output .= "Active Theme Name: " . esc_html( $theme_info->get( 'Name' ) );
			$output .= "\r\n";
			$output .= "Active Theme Version: " . esc_html( $theme_info->get( 'Version' ) );
			$output .= "\r\n";
			$output .= "Active Theme Folder: " . esc_html( $theme_info->get_stylesheet() );
			$output .= "\r\n";

			if ( is_child_theme() ) {
				$parent_info = $theme_info->parent();

				if ( ! empty( $parent_info ) && is_a( $parent_info, 'WP_Theme' ) ) {
					$output .= "Parent Theme Name: " . esc_html( $parent_info->get( 'Name' ) );
					$output .= "\r\n";
					$output .= "Parent Theme Version: " . esc_html( $parent_info->get( 'Version' ) );
					$output .= "\r\n";
					$output .= "Parent Theme Folder: " . esc_html( $parent_info->get_stylesheet() );
					$output .= "\r\n";
				} else {
					$output .= "WARNING: Parent theme metadata not found\r\n";
				}
			}
			if ( ! file_exists( $theme_info->get_stylesheet_directory() ) ) {
				$output .= "WARNING: Active theme folder not found\r\n";
			}
		} else {
			$output .= "WARNING: Theme metadata not found\r\n";
		}

		$output .= "\r\n";

		$output         .= "Active Plugins:\r\n";
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$plugin_details = array();

		if ( is_multisite() ) {
			$network_active_plugins = wp_get_active_network_plugins();
			$active_plugins         = array_map( array( $this, 'remove_wp_plugin_dir' ), $network_active_plugins );
		}

		foreach ( $active_plugins as $plugin ) {
			$plugin_details[] = $this->get_plugin_details( WP_PLUGIN_DIR . '/' . $plugin );
		}

		asort( $plugin_details );
		$output .= implode( '', $plugin_details );

		$mu_plugins = wp_get_mu_plugins();
		if ( $mu_plugins ) {
			$mu_plugin_details = array();
			$output            .= "\r\n";
			$output            .= "Must-use Plugins:\r\n";

			foreach ( $mu_plugins as $mu_plugin ) {
				$mu_plugin_details[] = $this->get_plugin_details( $mu_plugin );
			}

			asort( $mu_plugin_details );
			$output .= implode( '', $mu_plugin_details );
		}

		$dropins = get_dropins();
		if ( $dropins ) {
			$output .= "\r\n\r\n";
			$output .= "Drop-ins:\r\n";

			foreach ( $dropins as $file => $dropin ) {
				$output .= $file . ( isset( $dropin['Name'] ) ? ' - ' . $dropin['Name'] : '' );
				$output .= "\r\n";
			}
		}

		return $output;
	}

	/**
	 * Helper to display plugin details
	 *
	 * @param string $plugin_path
	 * @param string $suffix
	 *
	 * @return string
	 */
	function get_plugin_details( $plugin_path, $suffix = '' ) {
		$plugin_data = get_plugin_data( $plugin_path );
		if ( empty( $plugin_data['Name'] ) ) {
			return basename( $plugin_path );
		}

		return sprintf( "%s%s (v%s) by %s\r\n", $plugin_data['Name'], $suffix, $plugin_data['Version'], strip_tags( $plugin_data['AuthorName'] ) );
	}

	/**
	 * Helper to remove the plugin directory from the plugin path
	 *
	 * @param string $path Absolute plugin file path
	 *
	 * @return string
	 */
	public function remove_wp_plugin_dir( $path ) {
		$plugin_dir = trailingslashit( WP_PLUGIN_DIR );
		$plugin     = str_replace( $plugin_dir, '', $path );

		return $plugin;
	}

	/**
	 * Check for as3cf-download-log and related nonce and if found begin the
	 * download of the diagnostic log
	 *
	 * @return void
	 */
	private function http_prepare_download_log() {
		if ( isset( $_GET['as3cf-download-log'] ) && wp_verify_nonce( $_GET['nonce'], 'as3cf-download-log' ) ) {
			$log      = $this->output_diagnostic_info();
			$url      = parse_url( home_url() );
			$host     = sanitize_file_name( $url['host'] );
			$filename = sprintf( '%s-diagnostic-log-%s.txt', $host, date( 'YmdHis' ) );
			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Length: ' . strlen( $log ) );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			echo $log;
			exit;
		}
	}

	/**
	 * Return human friendly ACL name
	 *
	 * @param string $acl
	 *
	 * @return string
	 */
	function get_acl_display_name( $acl ) {
		$acl = empty( $acl ) ? 'default' : $acl;
		$acl = ( 'public-read' === $acl ) ? 'public' : $acl;

		return ucwords( str_replace( '-', ' ', $acl ) );
	}

	/**
	 * Detect if OpenSSL is enabled
	 *
	 * @return bool
	 */
	function open_ssl_enabled() {
		if ( defined( 'OPENSSL_VERSION_TEXT' ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Detect if PHP GD is enabled
	 *
	 * @return bool
	 */
	function gd_enabled() {
		if ( extension_loaded( 'gd' ) && function_exists( 'gd_info' ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Detect is Imagick is enabled
	 *
	 * @return bool
	 */
	function imagick_enabled() {
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) && class_exists( 'ImagickPixel' ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Is the current blog ID that specified in wp-config.php
	 *
	 * @param int $blog_id
	 *
	 * @return bool
	 */
	function is_current_blog( $blog_id ) {
		$default = defined( 'BLOG_ID_CURRENT_SITE' ) ? BLOG_ID_CURRENT_SITE : 1;

		if ( $default === $blog_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Helper to switch to a Multisite blog
	 *  - If the site is MS
	 *  - If the blog is not the current blog defined
	 *
	 * @param int|bool $blog_id
	 */
	public function switch_to_blog( $blog_id = false ) {
		if ( ! is_multisite() ) {
			return;
		}

		if ( ! $blog_id ) {
			$blog_id = defined( 'BLOG_ID_CURRENT_SITE' ) ? BLOG_ID_CURRENT_SITE : 1;
		}

		if ( $blog_id !== get_current_blog_id() ) {
			switch_to_blog( $blog_id );
		}
	}

	/**
	 * Helper to restore to the current Multisite blog
	 */
	public function restore_current_blog() {
		if ( is_multisite() ) {
			restore_current_blog();
		}
	}

	/**
	 * Used to give a realistic total of storage space used on a Multisite subsite,
	 * when there have been attachments uploaded to S3 but removed from server
	 *
	 * @param bool $space_used
	 *
	 * @return float|int
	 */
	function multisite_get_space_used( $space_used ) {
		global $wpdb;

		// Sum the total file size (including image sizes) for all S3 attachments
		$sql = "SELECT SUM( meta_value ) AS bytes_total
				FROM {$wpdb->postmeta}
				WHERE meta_key = 'as3cf_filesize_total'";

		$space_used = $wpdb->get_var( $sql );

		// Get local upload sizes
		$upload_dir = wp_upload_dir();
		$space_used += get_dirsize( $upload_dir['basedir'] );

		if ( $space_used > 0 ) {
			// Convert to bytes to MB
			$space_used = $space_used / 1024 / 1024;
		}

		return $space_used;
	}

	/**
	 * Memory exceeded
	 *
	 * Ensures the a process never exceeds 90% of the maximum WordPress memory.
	 *
	 * @param null|string $filter_name Name of filter to apply to the return
	 *
	 * @return bool
	 */
	public function memory_exceeded( $filter_name = null ) {
		$memory_limit   = $this->get_memory_limit() * 0.9; // 90% of max memory
		$current_memory = memory_get_usage( true );
		$return         = false;

		if ( $current_memory >= $memory_limit ) {
			$return = true;
		}

		if ( is_null( $filter_name ) || ! is_string( $filter_name ) ) {
			return $return;
		}

		return apply_filters( $filter_name, $return );
	}

	/**
	 * Get memory limit
	 *
	 * @return int
	 */
	public function get_memory_limit() {
		if ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		} else {
			// Sensible default
			$memory_limit = '128M';
		}

		if ( ! $memory_limit || -1 == $memory_limit ) {
			// Unlimited, set to 32GB
			$memory_limit = '32000M';
		}

		return wp_convert_hr_to_bytes( $memory_limit );
	}

	/**
	 * Get the total attachment and total offloaded/not offloaded attachment counts
	 *
	 * @param bool $skip_transient Whether to force database query and skip transient, default false
	 * @param bool $force          Whether to force database query and skip static cache, implies $skip_transient, default false
	 * @param int  $forced_blog_id Optional, restrict the force count of media items to only this blog ID, ignored if $force is false
	 *
	 * @return array
	 */
	public function media_counts( bool $skip_transient = false, bool $force = false, int $forced_blog_id = 0 ): array {
		static $table_prefixes;
		static $table_prefix_count;

		if ( $skip_transient || false === ( $attachment_counts = get_site_transient( 'as3cf_attachment_counts' ) ) ) {
			if ( empty( $table_prefixes ) || ! empty( $forced_blog_id ) ) {
				$table_prefixes     = AS3CF_Utils::get_all_blog_table_prefixes();
				$table_prefix_count = count( $table_prefixes );
			}

			$attachment_counts = array(
				'total'         => 0,
				'not_offloaded' => 0,
				'offloaded'     => 0,
				'summaries'     => array(),
			);

			$skip_transient_requested = $skip_transient;
			$force_requested          = $force;

			foreach ( $table_prefixes as $blog_id => $table_prefix ) {
				$this->switch_to_blog( $blog_id );

				$skip_transient = $skip_transient_requested;
				$force          = $force_requested;

				// If forcing an update from database for a specific blog ID, get others from transients if possible.
				if ( $force && ! empty( $forced_blog_id ) && $forced_blog_id > 0 && $forced_blog_id !== $blog_id ) {
					$skip_transient = false;
					$force          = false;
				}

				// If on a multisite and not doing a blog specific update, don't skip transient.
				if ( is_multisite() && $skip_transient && empty( $forced_blog_id ) ) {
					$skip_transient = false;
					$force          = false;
				}

				/** @var Item $class */
				foreach ( $this->get_source_type_classes() as $class ) {
					$counts = $class::count_items( $skip_transient, $force, $blog_id );

					$attachment_counts['total']         += $counts['total'];
					$attachment_counts['offloaded']     += $counts['offloaded'];
					$attachment_counts['not_offloaded'] += $counts['not_offloaded'];

					if ( $class::summary_enabled() ) {
						if ( ! isset( $attachment_counts['summaries'][ $class::summary_type() ] ) ) {
							$attachment_counts['summaries'][ $class::summary_type() ] = array(
								'total'         => 0,
								'offloaded'     => 0,
								'not_offloaded' => 0,
							);
						}

						$attachment_counts['summaries'][ $class::summary_type() ]['total']         += $counts['total'];
						$attachment_counts['summaries'][ $class::summary_type() ]['offloaded']     += $counts['offloaded'];
						$attachment_counts['summaries'][ $class::summary_type() ]['not_offloaded'] += $counts['not_offloaded'];
					}
				}

				$this->restore_current_blog();
			}

			ksort( $attachment_counts );

			// Large site defaults to transient timeout of 5 minutes.
			$timeout = 5;

			// For smaller media counts we can reduce the timeout to make changes more responsive
			// without noticeably impacting performance, as long as there aren't so many subsites.
			if ( 5000 > $attachment_counts['total'] && 50 > $table_prefix_count ) {
				$timeout = 0;
			} elseif ( 50000 > $attachment_counts['total'] && 500 > $table_prefix_count ) {
				$timeout = 2;
			}

			/**
			 * How many minutes should total media counts be cached?
			 *
			 * Min: 0 minutes.
			 * Max: 1 day (1440 minutes).
			 *
			 * Default 0 for small media counts, 2 for medium (5k <= X < 50k), 5 for larger (>= 50k).
			 * However, on a multisite, 0 is only allowed for < 50 subsites, 2 for < 500 subsite, otherwise it's 5.
			 *
			 * @param int $minutes
			 *
			 * @retun int
			 */
			$timeout = min( max( 0, (int) apply_filters( 'as3cf_media_counts_timeout', $timeout ) ), 1440 );

			// We lied, our real minimum is 3 seconds to ensure there's at least a tiny bit of caching,
			// which helps combat some potential race conditions, and makes sure the transient has a timeout.
			$timeout = max( $timeout, 0.05 );

			set_site_transient( 'as3cf_attachment_counts', $attachment_counts, $timeout * MINUTE_IN_SECONDS );
		}

		return $attachment_counts;
	}

	/**
	 * Check the existence of the items table (as3cf_items). Returns an array with one row per
	 * possible database prefix (multisite support).
	 *
	 * @param bool $all            Return all tables or just missing tables. Defaults to all/true.
	 * @param bool $skip_transient Whether to force database query and skip transient, default false.
	 *
	 * @return array
	 */
	private function get_db_init_status( $all = true, $skip_transient = false ) {
		global $wpdb;

		if ( $skip_transient || false === ( $db_init_status = get_site_transient( 'as3cf_db_init_status' ) ) ) {
			$table_prefixes = AS3CF_Utils::get_all_blog_table_prefixes();

			$db_init_status = array();

			foreach ( $table_prefixes as $blog_id => $table_prefix ) {
				$table_name = $table_prefix . Item::ITEMS_TABLE;

				$db_init_status[ $blog_id ] = array(
					'name'   => $table_name,
					'status' => false,
				);

				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
					$db_init_status[ $blog_id ]['status'] = true;
				}
			}

			set_site_transient( 'as3cf_db_init_status', $db_init_status, 5 * MINUTE_IN_SECONDS );
		}

		if ( ! $all ) {
			$db_init_status = array_filter( $db_init_status, function ( $table ) {
				return false === $table['status'];
			} );
		}

		return $db_init_status;
	}

	/**
	 * Display a notice after either lite or pro plugin has been auto deactivated
	 */
	function plugin_deactivated_notice() {
		if ( false !== ( $deactivated_notice_id = get_transient( 'as3cf_deactivated_notice_id' ) ) ) {
			if ( '1' === $deactivated_notice_id ) {
				$title   = __( 'WP Offload Media Activation', 'amazon-s3-and-cloudfront' );
				$message = __( "WP Offload Media Lite and WP Offload Media cannot both be active. We've automatically deactivated WP Offload Media Lite.", 'amazon-s3-and-cloudfront' );
			} else {
				$title   = __( 'WP Offload Media Lite Activation', 'amazon-s3-and-cloudfront' );
				$message = __( "WP Offload Media Lite and WP Offload Media cannot both be active. We've automatically deactivated WP Offload Media.", 'amazon-s3-and-cloudfront' );
			}

			$message = sprintf( '<strong>%s</strong> &mdash; %s', esc_html( $title ), esc_html( $message ) );

			$this->render_view( 'notice', array( 'message' => $message ) );

			delete_transient( 'as3cf_deactivated_notice_id' );
		}
	}

	/**
	 * Throw error
	 *
	 * @param string $code
	 * @param string $message
	 * @param mixed  $data
	 *
	 * @return WP_Error
	 */
	public function _throw_error( $code, $message = '', $data = '' ) { //@phpcs:ignore
		return new WP_Error( $code, $message, $data );
	}

	/**
	 * Get UTM source for plugin.
	 *
	 * @return string
	 */
	protected static function get_utm_source() {
		return 'OS3+Free';
	}

	/**
	 * Get UTM content for WP Engine URL.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	protected static function get_wpe_url_utm_content( $content = 'plugin_footer_text' ) {
		return 'ome_free_' . $content;
	}

	/**
	 * More info link.
	 *
	 * @param string $path        Relative path on DBI site
	 * @param string $utm_content Optional utm_content value.
	 * @param string $hash        Optional hash anchor value without the '#'.
	 * @param string $text        Optional override of link text.
	 * @param string $prefix      Optional non-linked prefix text.
	 * @param string $suffix      Optional non-linked suffix text.
	 *
	 * @return string
	 */
	public static function more_info_link( $path, $utm_content = '', $hash = '', $text = '', $prefix = '', $suffix = '' ) {
		$args = array(
			'utm_campaign' => 'support+docs',
		);

		if ( ! empty( $utm_content ) ) {
			$args['utm_content'] = $utm_content;
		}

		$text   = empty( $text ) ? __( 'More&nbsp;info', 'amazon-s3-and-cloudfront' ) : $text;
		$prefix = empty( $prefix ) ? '' : $prefix;
		$suffix = empty( $suffix ) ? '' : $suffix;

		$url  = static::dbrains_url( $path, $args, $hash );
		$link = AS3CF_Utils::dbrains_link( $url, $text );

		return sprintf( '<span class="more-info">%s%s%s</span>', $prefix, $link, $suffix );
	}

	/**
	 * Settings more info link.
	 *
	 * @param string $hash
	 * @param string $text Optional override of link text.
	 * @param string $utm_content
	 *
	 * @return string
	 */
	public static function settings_more_info_link( string $hash, string $text = '', string $utm_content = '' ): string {
		return static::more_info_link( '/wp-offload-media/doc/settings/', $utm_content, $hash, $text );
	}

	/**
	 * Get an associative array of doc URLs and descriptions.
	 *
	 * @param array $docs_data Optional array of data to be merged with defaults.
	 *
	 * @return array
	 */
	protected function get_docs( $docs_data = array() ) {
		$docs = array();

		$_docs_data = array(
			// Storage Settings.
			'storage-provider'          => array( 'doc' => 'settings', 'hash' => 'storage-provider' ),
			'bucket'                    => array( 'doc' => 'settings', 'hash' => 'bucket' ),
			'region'                    => array( 'doc' => 'settings', 'hash' => 'bucket' ), // bucket section references region
			'copy-to-s3'                => array( 'doc' => 'settings', 'hash' => 'copy-to-s3' ),
			'enable-object-prefix'      => array( 'doc' => 'settings', 'hash' => 'object-prefix' ),
			'object-prefix'             => array( 'doc' => 'settings', 'hash' => 'object-prefix' ),
			'use-yearmonth-folders'     => array( 'doc' => 'settings', 'hash' => 'use-yearmonth-folders' ),
			'object-versioning'         => array( 'doc' => 'settings', 'hash' => 'object-versioning' ),
			'remove-local-file'         => array( 'doc' => 'settings', 'hash' => 'remove-local-file' ),
			// Delivery Settings.
			'delivery-provider'         => array( 'doc' => 'settings', 'hash' => 'delivery-provider' ),
			'serve-from-s3'             => array( 'doc' => 'settings', 'hash' => 'serve-from-s3' ),
			'enable-delivery-domain'    => array( 'doc' => 'settings', 'hash' => 'delivery-domain' ),
			'enable-signed-urls'        => array( 'doc' => 'settings', 'hash' => 'enable-signed-urls' ),
			'signed-urls-key-id'        => array( 'doc' => 'settings', 'hash' => 'signed-urls-key-id' ),
			'signed-urls-key-file-path' => array( 'doc' => 'settings', 'hash' => 'signed-urls-key-file-path' ),
			'signed-urls-object-prefix' => array( 'doc' => 'settings', 'hash' => 'signed-urls-object-prefix' ),
			'force-https'               => array( 'doc' => 'settings', 'hash' => 'force-https' ),
			// Pseudo Settings.
			'block-public-access'       => array( 'doc' => 'block-all-public-access-to-bucket' ),
			'object-ownership-enforced' => array( 'doc' => 'amazon-s3-bucket-object-ownership' ),
		);

		if ( ! empty( $docs_data ) ) {
			$_docs_data = array_merge( $_docs_data, $docs_data );
		}

		foreach ( apply_filters( $this->get_plugin_prefix() . '_docs_data', $_docs_data ) as $key => $data ) {
			$args = array( 'utm_campaign' => 'support+docs' );
			$hash = '';

			if ( empty( $data['doc'] ) ) {
				continue;
			}

			if ( ! empty( $data['args'] ) && is_array( $data['args'] ) ) {
				$args = array_merge( $args, $data['args'] );
			}

			if ( ! empty( $data['hash'] ) ) {
				$hash = $data['hash'];
			}

			$url = static::dbrains_url( '/wp-offload-media/doc/' . trailingslashit( $data['doc'] ), $args, $hash );

			if ( ! empty( $data['desc'] ) ) {
				$desc = $data['desc'];
			} else {
				$desc = _x( 'Click to view help doc on our site', 'Help icon alt text', 'amazon-s3-and-cloudfront' );
			}

			$docs[ $key ] = array(
				'url'  => $url,
				'desc' => $desc,
			);
		}

		return apply_filters( $this->get_plugin_prefix() . '_get_docs', $docs );
	}

	/**
	 * Helper function for filtering super globals. Easily testable.
	 *
	 * @param string $variable
	 * @param int    $type
	 * @param int    $filter
	 * @param mixed  $options
	 *
	 * @return mixed
	 */
	public function filter_input( $variable, $type = INPUT_GET, $filter = FILTER_DEFAULT, $options = array() ) {
		return filter_input( $type, $variable, $filter, $options );
	}

	/**
	 * Upgrade the 'virtual host' / 'bucket as domain' setting to the
	 * new CloudFront / Domain setting
	 *
	 * @return string
	 */
	public function upgrade_virtual_host() {
		$domain = 'cloudfront';
		$this->set_setting( 'cloudfront', $this->get_setting( 'bucket' ) );
		$this->set_setting( 'domain', $domain );

		$this->save_settings();

		return $domain;
	}

	/**
	 * Display a notice if using setting to force HTTP as url scheme, removed in 1.3.
	 */
	protected function maybe_display_deprecated_http_notice() {
		if ( 'http' !== $this->get_setting( 'ssl', 'request' ) || ! $this->is_plugin_setup() ) {
			return;
		}

		$notice_args = array(
			'type'              => 'notice-info',
			'only_show_to_user' => false,
			'flash'             => false,
		);

		$doc_url  = $this->dbrains_url( '/wp-offload-media/doc/force-http-setting/', array(
			'utm_campaign' => 'support+docs',
		) );
		$doc_link = AS3CF_Utils::dbrains_link( $doc_url, __( 'this doc' ) );

		$message = sprintf( '<strong>%s</strong> &mdash; ', __( 'WP Offload Media Feature Removed', 'amazon-s3-and-cloudfront' ) );
		$message .= sprintf( __( 'You had the "Always non-SSL" option selected in your settings, but we\'ve removed this option in version 1.3. We\'ll now use HTTPS when the request is HTTPS and regular HTTP when the request is HTTP. This should work fine for your site, but please take a poke around and make sure things are working ok. See %s for more details on why we did this and how you can revert back to the old behavior.', 'amazon-s3-and-cloudfront' ), $doc_link );

		$this->notices->add_notice( $message, $notice_args );
	}

	/**
	 * Potentially update path for delivery URLs.
	 *
	 * @param string   $path      Path in bucket to be used in URL.
	 * @param string   $domain    Domain to be used in URL.
	 * @param int|null $timestamp Optional time that signed URL expires.
	 *
	 * @return string
	 *
	 * Note: This is a wrapper for a filter, which only fires default (storage) delivery provider is not in use.
	 */
	public function maybe_update_delivery_path( $path, $domain, $timestamp = null ) {
		if ( static::get_default_delivery_provider() !== $this->get_delivery_provider()->get_provider_key_name() ) {
			$path_parts = apply_filters( 'as3cf_cloudfront_path_parts', explode( '/', $path ), $domain, $timestamp ); // Backwards compatibility.
			$path_parts = apply_filters( 'as3cf_delivery_domain_path_parts', $path_parts, $domain, $timestamp );

			if ( ! empty( $path_parts ) ) {
				$path = implode( '/', $path_parts );
			}
		}

		return $path;
	}

	/**
	 * Maybe remove query string from URL.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public function maybe_remove_query_string( $url ) {
		$parts = explode( '?', $url );

		return reset( $parts );
	}

	/**
	 * Ensure local URL is correct for multisite's non-primary subsites.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public function maybe_fix_local_subsite_url( $url ) {
		$siteurl = trailingslashit( get_option( 'siteurl' ) );

		if ( is_multisite() && ! $this->is_current_blog( get_current_blog_id() ) && 0 !== strpos( $url, $siteurl ) ) {
			// Replace original URL with subsite's current URL.
			$orig_siteurl = trailingslashit( apply_filters( 'as3cf_get_orig_siteurl', network_site_url() ) );
			$url          = str_replace( $orig_siteurl, $siteurl, $url );
		}

		return $url;
	}

	/**
	 * Get ACL for intermediate size.
	 *
	 * @param int                     $attachment_id
	 * @param string                  $size
	 * @param string                  $bucket     Optional bucket that ACL is potentially to be used with.
	 * @param Media_Library_Item|null $as3cf_item Optional item.
	 *
	 * @return string|null
	 */
	public function get_acl_for_intermediate_size( $attachment_id, $size, $bucket = null, Media_Library_Item $as3cf_item = null ) {
		if ( empty( $as3cf_item ) ) {
			$as3cf_item = Media_Library_Item::get_by_source_id( $attachment_id );
		}

		$acl = null;

		if ( $this->use_acl_for_intermediate_size( $attachment_id, $size, $bucket, $as3cf_item ) ) {
			$acl = $this->get_storage_provider()->get_default_acl();

			if ( ! empty( $as3cf_item ) ) {
				$acl = $as3cf_item->is_private( $size ) ? $this->get_storage_provider_instance( $as3cf_item->provider() )->get_private_acl() : $this->get_storage_provider_instance( $as3cf_item->provider() )->get_default_acl();
			}
		}

		return $acl;
	}

	/**
	 * Are ACLs in use for intermediate size on bucket?
	 *
	 * @param int       $attachment_id
	 * @param string    $size
	 * @param string    $bucket     Optional bucket that ACL is potentially to be used with.
	 * @param Item|null $as3cf_item Optional item.
	 *
	 * @return bool
	 */
	public function use_acl_for_intermediate_size( $attachment_id, $size, $bucket = null, Item $as3cf_item = null ) {
		// If this function is used without passing in an Item object, we're assuming $attachment
		if ( empty( $as3cf_item ) ) {
			$as3cf_item = Media_Library_Item::get_by_source_id( $attachment_id );
		}

		if ( empty( $bucket ) ) {
			$bucket = empty( $as3cf_item ) ? null : $as3cf_item->bucket();
		}

		if ( empty( $bucket ) ) {
			$bucket = $this->get_setting( 'bucket', null );
		}

		$use_acl            = apply_filters( 'as3cf_use_bucket_acls_for_intermediate_size', $this->get_setting( 'use-bucket-acls', true ), $attachment_id, $size, $bucket, $as3cf_item );
		$use_private_prefix = apply_filters( 'as3cf_enable_signed_urls_for_intermediate_size', $this->private_prefix_enabled(), $attachment_id, $size, $bucket, $as3cf_item );

		// If signed custom URLs are in play, and we have a private object, usually you can not use ACLs.
		if ( $use_acl && $use_private_prefix && ! empty( $as3cf_item ) && $as3cf_item->is_private( $size ) ) {
			$use_acl = false;
		}

		// Allow complete override if signed custom URLs and ACLs do play nice together some how, or other factors in play.
		return apply_filters( 'as3cf_use_acl_for_intermediate_size', $use_acl, $attachment_id, $size, $bucket, $as3cf_item );
	}

	/**
	 * Get all defined addons that use this plugin
	 *
	 * @param bool $unfiltered
	 *
	 * @return array
	 */
	public function get_addons( $unfiltered = false ) {
		$addons = $this->get_available_addons();

		if ( $unfiltered ) {
			return $addons;
		}

		return apply_filters( 'as3cf_addons', $addons );
	}

	/**
	 * @return array
	 */
	protected function get_available_addons(): array {
		return array();
	}

	/**
	 * Get the URL of the addon's icon
	 *
	 * @param string $slug
	 *
	 * @return string
	 */
	function get_addon_icon_url( $slug ) {
		$filename = str_replace( 'amazon-s3-and-cloudfront-', '', $slug );
		$filename = 'icon-' . $filename . '.svg';

		return plugins_url( 'assets/img/' . $filename, $this->plugin_file_path );
	}

	/**
	 * Polyfill for displaying "Settings saved." consistently between single-site and multisite environments.
	 *
	 * TL;DR: options-head.php is loaded for options-general.php (single sites only) which does this, but not on multisite.
	 *
	 * @see https://github.com/WordPress/WordPress/blob/c2d709e9d6cbe7f9b3c37da0a7c9aae788158124/wp-admin/admin-header.php#L265-L266
	 * @see https://github.com/WordPress/WordPress/blob/9b68e5953406024c75b92f7ebe2aef0385c8956e/wp-admin/options-head.php#L13-L16
	 */
	public function settings_saved_notice() {
		if ( isset( $_GET['updated'] ) && isset( $_GET['page'] ) ) {
			// For back-compat with plugins that don't use the Settings API and just set updated=1 in the redirect.
			add_settings_error( 'general', 'settings_updated', __( 'Settings saved.' ), 'updated' );
		}

		settings_errors();
	}

	/**
	 * Migrate access keys from AWS database setting to this plugin's settings record and raise any notices.
	 */
	private function handle_aws_access_key_migration() {
		if ( is_plugin_active( 'amazon-web-services/amazon-web-services.php' ) ) {
			add_action( 'aws_access_key_form_header', array( $this, 'handle_aws_access_key_form_header' ) );

			$message = sprintf(
				__( '<strong>Amazon Web Services Plugin No Longer Required</strong> &mdash; As of version 1.6 of WP Offload Media, the <a href="%1$s">Amazon Web Services</a> plugin is no longer required. We have removed the dependency by bundling a small portion of the AWS SDK into WP Offload Media. As long as none of your other active plugins or themes depend on the Amazon Web Services plugin, it should be safe to deactivate and delete it. %2$s', 'amazon-s3-and-cloudfront' ),
				'https://wordpress.org/plugins/amazon-web-services/',
				static::more_info_link( '/wp-offload-s3-1-6-released/', 'os3+settings+aws+active' )
			);
			$args    = array(
				'only_show_to_user'     => false,
				'only_show_in_settings' => true,
				'custom_id'             => 'aws-plugin-no-longer-required',
			);
			$this->notices->add_notice( $message, $args );

			if ( is_a( $this->get_storage_provider(), '\DeliciousBrains\WP_Offload_Media\Providers\Storage\AWS_Provider' ) && $this->get_storage_provider()->needs_access_keys() ) {
				// Have access keys been defined in still active AWS plugin's database settings?
				$aws_settings = get_site_option( 'aws_settings' );

				// If both AWS keys set and we already have a bucket set, safe to use the AWS keys.
				if ( ! empty( $aws_settings['access_key_id'] ) && ! empty( $aws_settings['secret_access_key'] ) && false !== $this->get_setting( 'bucket' ) ) {
					$this->set_setting( 'access-key-id', $aws_settings['access_key_id'] );
					$this->set_setting( 'secret-access-key', $aws_settings['secret_access_key'] );
					$this->save_settings();
				}
			}
		} else {
			$this->notices->remove_notice_by_id( 'aws-plugin-no-longer-required' );
		}
	}

	/**
	 * Create message in AWS access key form that this plugin no longer uses those settings.
	 */
	public function handle_aws_access_key_form_header() {
		$notice['message'] = sprintf(
			__( '<strong>WP Offload Media Settings Moved</strong> &mdash; You now define your AWS keys for WP Offload Media in the new <a href="%1$s">Settings tab</a>. Saving settings in the form below will have no effect on WP Offload Media. %2$s', 'amazon-s3-and-cloudfront' ),
			$this->get_plugin_page_url( array( 'hash' => 'settings' ) ),
			static::more_info_link( '/wp-offload-s3-1-6-released/', 'aws+os3+access+keys+setting+moved' )
		);
		$notice['inline']  = true;

		$this->render_view( 'notice', $notice );
	}

	/**
	 * Is there an upgrade in progress?
	 *
	 * @return bool
	 */
	public function is_upgrading() {
		return Upgrade::is_locked();
	}

	/**
	 * Get an array of locked notifications for the upgrades.
	 *
	 * @return array
	 */
	public function get_upgrade_locked_notifications() {
		return apply_filters( 'as3cf_get_upgrade_locked_notifications', array() );
	}

	/**
	 * Get running upgrade's name.
	 *
	 * @return string
	 */
	public function get_running_upgrade() {
		return apply_filters( 'as3cf_get_running_upgrade', '' );
	}

	/**
	 * Get an array of upgrade specific information.
	 *
	 * @return array
	 */
	public function get_upgrades_info() {
		return array(
			'is_upgrading'         => $this->is_upgrading(),
			'locked_notifications' => $this->get_upgrade_locked_notifications(),
			'running_upgrade'      => $this->get_running_upgrade(),
		);
	}

	/**
	 * Do current settings allow for private prefix to be used?
	 *
	 * @return bool
	 */
	public function private_prefix_enabled() {
		$delivery_provider = $this->get_delivery_provider();

		if ( empty( $delivery_provider ) || ! $delivery_provider->use_signed_urls_key_file_allowed() ) {
			return false;
		}

		if (
			$this->get_setting( 'enable-delivery-domain', false ) &&
			$this->get_setting( 'enable-signed-urls', false ) &&
			! empty( $this->get_setting( 'signed-urls-object-prefix' ) )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Register an item source type name and class
	 *
	 * @param string $source_type
	 * @param string $class
	 */
	public function register_source_type( string $source_type, string $class ) {
		$this->source_type_classes[ $source_type ] = $class;
	}

	/**
	 * Get Item type class from item source type identifier
	 *
	 * @param string $source_type
	 *
	 * @return string|false
	 */
	public function get_source_type_class( string $source_type ) {
		if ( isset( $this->source_type_classes[ $source_type ] ) ) {
			return $this->source_type_classes[ $source_type ];
		}

		return false;
	}

	/**
	 * Get Item type human friendly name item source type identifier
	 *
	 * @param string $source_type
	 *
	 * @return string|false
	 */
	public function get_source_type_name( string $source_type ) {
		/** @var Item $class */
		$class = $this->get_source_type_class( $source_type );
		if ( ! empty( $class ) ) {
			return $class::source_type_name();
		}

		return false;
	}

	/**
	 * Get all registered Item classes
	 *
	 * @return array
	 */
	public function get_source_type_classes(): array {
		return $this->source_type_classes;
	}

	/**
	 * Register an item summary type name and class
	 *
	 * @param string $summary_type
	 * @param string $class
	 */
	public function register_summary_type( string $summary_type, string $class ) {
		$this->summary_type_classes[ $summary_type ] = $class;
	}

	/**
	 * Get Item type class from item summary type identifier
	 *
	 * @param string $summary_type
	 *
	 * @return string|false
	 */
	public function get_summary_type_class( string $summary_type ) {
		if ( isset( $this->summary_type_classes[ $summary_type ] ) ) {
			return $this->summary_type_classes[ $summary_type ];
		}

		return false;
	}

	/**
	 * Get Item type human friendly name item summary type identifier
	 *
	 * @param string $summary_type
	 *
	 * @return string
	 */
	public function get_summary_type_name( string $summary_type ): string {
		/** @var Item $class */
		$class = $this->get_summary_type_class( $summary_type );
		if ( ! empty( $class ) ) {
			return $class::summary_type_name();
		}

		return __( 'Unknown', 'amazon-s3-and-cloudfront' );
	}

	/**
	 * Get all Item classes that have a registered summary type
	 *
	 * @return array
	 */
	public function get_summary_type_classes(): array {
		return $this->source_type_classes;
	}

	/**
	 * Get an array of offloaded media summary counts.
	 *
	 * @return array
	 */
	public function get_summary_counts(): array {
		$summaries = array();

		$counts = $this->media_counts();

		if ( ! empty( $counts['summaries'] ) ) {
			foreach ( $counts['summaries'] as $summary_type => $summary ) {
				$summary['type'] = $summary_type;
				$summary['name'] = $this->get_summary_type_name( $summary_type );
				$summaries[]     = $summary;
			}
		}

		return apply_filters( 'as3cf_get_summary_counts', $summaries );
	}

	/**
	 * Returns the Item_Handler instance for the given handler type.
	 *
	 * @param string $handler_type
	 *
	 * @return Item_Handler
	 */
	public function get_item_handler( $handler_type ) {
		if ( isset( $this->item_handlers[ $handler_type ] ) ) {
			return $this->item_handlers[ $handler_type ];
		}

		switch ( $handler_type ) {
			case Upload_Handler::get_item_handler_key_name():
				$this->item_handlers[ $handler_type ] = new Upload_Handler( $this );
				break;
			case Download_Handler::get_item_handler_key_name():
				$this->item_handlers[ $handler_type ] = new Download_Handler( $this );
				break;
			case Remove_Local_Handler::get_item_handler_key_name():
				$this->item_handlers[ $handler_type ] = new Remove_Local_Handler( $this );
				break;
			case Remove_Provider_Handler::get_item_handler_key_name():
				$this->item_handlers[ $handler_type ] = new Remove_Provider_Handler( $this );
				break;
			default:
				return null;
		}

		return $this->item_handlers[ $handler_type ];
	}

	/**
	 * Get notifications.
	 *
	 * @param string $tab      Optionally restrict to notifications for a specific tab.
	 * @param bool   $all_tabs Optionally return all tab specific notices regardless of tab.
	 *
	 * @return array
	 */
	public function get_notifications( $tab = '', $all_tabs = false ) {
		return $this->notices->get_notices( $tab, $all_tabs );
	}

	/**
	 * Dismiss notification.
	 *
	 * @param string $id Notification ID.
	 */
	public function dismiss_notification( $id ) {
		$this->notices->dismiss_notice( $id );
	}

	/**
	 * Get URLs needed by the frontend.
	 *
	 * @return array
	 */
	public function get_js_urls(): array {
		$region = $this->get_setting( 'region' );

		if ( is_wp_error( $region ) ) {
			$region = '';
		}

		return apply_filters( 'as3cf_js_urls', array(
			'api'                                    => esc_url_raw( rest_url() ),
			'settings'                               => $this->get_plugin_page_url(),
			'home'                                   => network_home_url(),
			'home_domain'                            => parse_url( network_home_url(), PHP_URL_HOST ),
			'admin'                                  => network_admin_url(),
			'assets'                                 => plugins_url( 'assets/', $this->get_plugin_file_path() ),
			'url_example'                            => $this->get_url_preview(),
			'url_parts'                              => $this->get_url_preview( true ),
			'storage_provider_console_base'          => $this->get_storage_provider()->get_console_url(),
			'storage_provider_console_prefix_param'  => $this->get_storage_provider()->get_console_url_prefix_param(),
			'storage_provider_console_url'           => $this->get_storage_provider()->get_console_url(
				$this->get_setting( 'bucket' ),
				$this->get_object_prefix(),
				$region
			),
			'delivery_provider_console_base'         => $this->get_delivery_provider()->get_console_url(),
			'delivery_provider_console_prefix_param' => $this->get_delivery_provider()->get_console_url_prefix_param(),
			'delivery_provider_console_url'          => $this->get_delivery_provider()->get_console_url(
				$this->get_setting( 'bucket' ),
				$this->get_object_prefix(),
				$region
			),
			'pricing'                                => $this->dbrains_url( '/wp-offload-media/pricing/', array( 'utm_campaign' => 'WP+Offload+S3' ) ),
			'header_discount'                        => $this->dbrains_url( '/wp-offload-media/pricing/', array( 'utm_campaign' => 'WP+Offload+S3', 'utm_content' => 'header' ) ),
			'upsell_discount'                        => $this->dbrains_url( '/wp-offload-media/pricing/', array( 'utm_campaign' => 'WP+Offload+S3', 'utm_content' => 'upsell' ) ),
			'sidebar_plugin'                         => $this->dbrains_url( '/wp-offload-media/', array( 'utm_campaign' => 'WP+Offload+S3', 'utm_content' => 'sidebar' ) ),
			'sidebar_discount'                       => $this->dbrains_url( '/wp-offload-media/pricing/', array( 'utm_campaign' => 'WP+Offload+S3', 'utm_content' => 'sidebar' ) ),
			'upsell_discount_assets'                 => $this->dbrains_url( '/wp-offload-media/pricing/', array( 'utm_campaign' => 'WP+Offload+S3', 'utm_content' => 'upsell_assets' ) ),
			'upsell_discount_tools'                  => $this->dbrains_url( '/wp-offload-media/pricing/', array( 'utm_campaign' => 'WP+Offload+S3', 'utm_content' => 'upsell_tools' ) ),
			'sidebar_dbi'                            => 'https://wpengine.com/developers/',
			'download_diagnostics'                   => $this->get_plugin_page_url(
				array(
					'nonce'              => wp_create_nonce( 'as3cf-download-log' ),
					'as3cf-download-log' => '1',
					'hash'               => '/support',
				),
				'network',
				false
			),
		) );
	}

	/**
	 * Get an upsell message promoting that there is media that could be offloaded.
	 *
	 * @return string
	 */
	public function get_offload_remaining_upsell_message(): string {
		$counts = $this->media_counts();

		if ( ! empty( $counts['not_offloaded'] ) ) {
			if ( 1 === $counts['not_offloaded'] ) {
				return sprintf( __( 'Upgrade to offload %d remaining media item', 'amazon-s3-and-cloudfront' ), number_format_i18n( $counts['not_offloaded'] ) );
			} else {
				return sprintf( __( 'Upgrade to offload %d remaining media items', 'amazon-s3-and-cloudfront' ), number_format_i18n( $counts['not_offloaded'] ) );
			}
		}

		return '';
	}
}
