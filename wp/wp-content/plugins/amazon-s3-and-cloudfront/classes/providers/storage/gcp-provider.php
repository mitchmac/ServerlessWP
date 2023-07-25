<?php

namespace DeliciousBrains\WP_Offload_Media\Providers\Storage;

use AS3CF_Plugin_Base;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\Cloud\Core\Exception\GoogleException;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\Cloud\Core\ServiceBuilder;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\Cloud\Storage\Bucket;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\Cloud\Storage\StorageClient;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\Cloud\Storage\StorageObject;
use DeliciousBrains\WP_Offload_Media\Providers\Storage\Streams\GCP_GCS_Stream_Wrapper;
use Exception;
use WP_Error;

class GCP_Provider extends Storage_Provider {

	/**
	 * @var ServiceBuilder
	 */
	private $cloud;

	/**
	 * @var StorageClient
	 */
	private $storage;

	/**
	 * @var string
	 */
	protected static $provider_name = 'Google Cloud Platform';

	/**
	 * @var string
	 */
	protected static $provider_short_name = 'GCP';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $provider_key_name = 'gcp';

	/**
	 * @var string
	 */
	protected static $service_name = 'Google Cloud Storage';

	/**
	 * @var string
	 */
	protected static $service_short_name = 'GCS';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $service_key_name = 'gcs';

	/**
	 * Optional override of "Provider Name" + "Service Name" for friendly name for service.
	 *
	 * @var string
	 */
	protected static $provider_service_name = 'Google Cloud Storage';

	/**
	 * The slug for the service's quick start guide doc.
	 *
	 * @var string
	 */
	protected static $provider_service_quick_start_slug = 'google-cloud-storage-quick-start-guide';

	/**
	 * @var array
	 */
	protected static $use_server_roles_constants = array(
		'AS3CF_GCP_USE_GCE_IAM_ROLE',
	);

	/**
	 * @var array
	 */
	protected static $key_file_path_constants = array(
		'AS3CF_GCP_KEY_FILE_PATH',
	);

	/**
	 * @var array
	 */
	protected static $regions = array(
		'asia'                    => 'Multi-Region (Asia)',
		'eu'                      => 'Multi-Region (EU)',
		'us'                      => 'Multi-Region (US)',
		'us-central1'             => 'North America (Iowa)',
		'us-east1'                => 'North America (South Carolina)',
		'us-east4'                => 'North America (Northern Virginia)',
		'us-east5'                => 'North America (Columbus)',
		'us-west1'                => 'North America (Oregon)',
		'us-west2'                => 'North America (Los Angeles)',
		'us-west3'                => 'North America (Salt Lake City)',
		'us-west4'                => 'North America (Las Vegas)',
		'us-south1'               => 'North America (Dallas)',
		'northamerica-northeast1' => 'North America (Montréal)',
		'northamerica-northeast2' => 'North America (Toronto)',
		'southamerica-east1'      => 'South America (São Paulo)',
		'southamerica-west1'      => 'South America (Santiago)',
		'europe-central2'         => 'Europe (Warsaw)',
		'europe-north1'           => 'Europe (Finland)',
		'europe-west1'            => 'Europe (Belgium)',
		'europe-west2'            => 'Europe (London)',
		'europe-west3'            => 'Europe (Frankfurt)',
		'europe-west4'            => 'Europe (Netherlands)',
		'europe-west6'            => 'Europe (Zürich)',
		'europe-west8'            => 'Europe (Milan)',
		'europe-west9'            => 'Europe (Paris)',
		'europe-southwest1'       => 'Europe (Madrid)',
		'me-west1'                => 'Middle East (Tel Aviv)',
		'asia-east1'              => 'Asia (Taiwan)',
		'asia-east2'              => 'Asia (Hong Kong)',
		'asia-northeast1'         => 'Asia (Tokyo)',
		'asia-northeast2'         => 'Asia (Osaka)',
		'asia-northeast3'         => 'Asia (Seoul)',
		'asia-southeast1'         => 'Asia (Singapore)',
		'asia-south1'             => 'India (Mumbai)',
		'asia-south2'             => 'India (Dehli)',
		'asia-southeast2'         => 'Indonesia (Jakarta)',
		'australia-southeast1'    => 'Australia (Sydney)',
		'australia-southeast2'    => 'Australia (Melbourne)',
		'asia1'                   => 'Dual-Region (Tokyo/Osaka)',
		'eur4'                    => 'Dual-Region (Finland/Netherlands)',
		'nam4'                    => 'Dual-Region (Iowa/South Carolina)',
	);

	/**
	 * @var string
	 */
	protected static $default_region = 'us';

	/**
	 * @var string
	 */
	protected $default_domain = 'storage.googleapis.com';

	/**
	 * @var string
	 */
	protected $console_url = 'https://console.cloud.google.com/storage/browser/';

	/**
	 * @var string
	 */
	protected $console_url_prefix_param = '/';

	const PUBLIC_ACL  = 'publicRead';
	const PRIVATE_ACL = 'projectPrivate';

	/**
	 * GCP_Provider constructor.
	 *
	 * @param AS3CF_Plugin_Base $as3cf
	 */
	public function __construct( AS3CF_Plugin_Base $as3cf ) {
		parent::__construct( $as3cf );

		// Autoloader.
		require_once $as3cf->get_plugin_sdks_dir_path() . '/Gcp/autoload.php';
	}

	/**
	 * Returns default args array for the client.
	 *
	 * @return array
	 */
	protected function default_client_args() {
		return array();
	}

	/**
	 * Process the args before instantiating a new client for the provider's SDK.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	protected function init_client_args( array $args ) {
		return $args;
	}

	/**
	 * Instantiate a new client for the provider's SDK.
	 *
	 * @param array $args
	 */
	protected function init_client( array $args ) {
		$this->cloud = new ServiceBuilder( $args );
	}

	/**
	 * Process the args before instantiating a new service specific client.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	protected function init_service_client_args( array $args ) {
		return $args;
	}

	/**
	 * Instantiate a new service specific client.
	 *
	 * @param array $args
	 *
	 * @return StorageClient
	 */
	protected function init_service_client( array $args ) {
		$this->storage = $this->cloud->storage( $args );

		return $this->storage;
	}

	/**
	 * Make sure region "slug" fits expected format.
	 *
	 * @param string $region
	 *
	 * @return string
	 */
	public function sanitize_region( $region ) {
		if ( ! is_string( $region ) ) {
			// Don't translate any region errors
			return $region;
		}

		return strtolower( $region );
	}

	/**
	 * Create bucket.
	 *
	 * @param array $args
	 *
	 * @throws GoogleException
	 */
	public function create_bucket( array $args ) {
		$name = '';
		if ( ! empty( $args['Bucket'] ) ) {
			$name = $args['Bucket'];
			unset( $args['Bucket'] );
		}

		if ( ! empty( $args['LocationConstraint'] ) ) {
			$args['location'] = $args['LocationConstraint'];
			unset( $args['LocationConstraint'] );
		}

		$this->storage->createBucket( $name, $args );
	}

	/**
	 * Check whether bucket exists.
	 *
	 * @param string $bucket
	 *
	 * @return bool
	 */
	public function does_bucket_exist( $bucket ) {
		return $this->storage->bucket( $bucket )->exists();
	}

	/**
	 * Returns region for bucket.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	public function get_bucket_location( array $args ) {
		$info   = $this->storage->bucket( $args['Bucket'] )->info();
		$region = empty( $info['location'] ) ? '' : $info['location'];

		return $region;
	}

	/**
	 * List buckets.
	 *
	 * @param array $args
	 *
	 * @return array
	 * @throws GoogleException
	 */
	public function list_buckets( array $args = array() ) {
		$result = array();

		$buckets = $this->storage->buckets( $args );

		if ( ! empty( $buckets ) ) {
			/** @var Bucket $bucket */
			foreach ( $buckets as $bucket ) {
				$result['Buckets'][] = array(
					'Name' => $bucket->name(),
				);
			}
		}

		return $result;
	}

	/**
	 * Check whether key exists in bucket.
	 *
	 * @param string $bucket
	 * @param string $key
	 * @param array  $options
	 *
	 * @return bool
	 */
	public function does_object_exist( $bucket, $key, array $options = array() ) {
		return $this->storage->bucket( $bucket )->object( $key )->exists( $options );
	}

	/**
	 * Get public "canned" ACL string.
	 *
	 * @return string
	 */
	public function get_public_acl() {
		return static::PUBLIC_ACL;
	}

	/**
	 * Get private "canned" ACL string.
	 *
	 * @return string
	 */
	public function get_private_acl() {
		return static::PRIVATE_ACL;
	}

	/**
	 * Download object, destination specified in args.
	 *
	 * @see https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-s3-2006-03-01.html#getobject
	 * @see https://googleapis.github.io/google-cloud-php/#/docs/google-cloud/v0.90.0/storage/storageobject?method=downloadToFile
	 *
	 * @param array $args
	 */
	public function get_object( array $args ) {
		$this->storage->bucket( $args['Bucket'] )->object( $args['Key'] )->downloadToFile( $args['SaveAs'] );
	}

	/**
	 * Get object's URL.
	 *
	 * @param string $bucket
	 * @param string $key
	 * @param int    $timestamp
	 * @param array  $args
	 *
	 * @return string
	 */
	public function get_object_url( $bucket, $key, $timestamp, array $args = array() ) {
		if ( empty( $timestamp ) || ! is_int( $timestamp ) || $timestamp < 0 ) {
			$info = $this->storage->bucket( $bucket )->object( $key )->info();
			$link = empty( $info['selfLink'] ) ? '' : $info['selfLink'];

			return $link;
		} else {
			$options = array();

			if ( ! empty( $args['BaseURL'] ) ) {
				$options['cname'] = $args['BaseURL'];
			}

			return $this->storage->bucket( $bucket )->object( $key )->signedUrl( $timestamp, $options );
		}
	}

	/**
	 * List objects.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function list_objects( array $args = array() ) {
		$result = array();

		$objects = $this->storage->bucket( $args['Bucket'] )->objects( $args['Prefix'] );

		if ( ! empty( $objects ) ) {
			/** @var StorageObject $object */
			foreach ( $objects as $object ) {
				$info                 = $object->info();
				$result['Contents'][] = array(
					'Key'  => $object->name(),
					'Size' => $info['size'],
				);
			}
		}

		return $result;
	}

	/**
	 * Update the ACL for an object.
	 *
	 * @param array $args
	 *
	 * @throws Exception
	 */
	public function update_object_acl( array $args ) {
		if ( empty( $args['ACL'] ) ) {
			throw new Exception( __METHOD__ . ' called without "ACL" arg.' );
		}

		$this->storage->bucket( $args['Bucket'] )->object( $args['Key'] )->update( array( 'predefinedAcl' => $args['ACL'] ) );
	}

	/**
	 * Update the ACL for multiple objects.
	 *
	 * @param array $items
	 *
	 * @return array Failures with elements Key and Message
	 */
	public function update_object_acls( array $items ) {
		$failures = array();

		// Unfortunately the GCP PHP SDK does not have batch operations.
		foreach ( $items as $item ) {
			try {
				$this->update_object_acl( $item );
			} catch ( Exception $e ) {
				$failures[] = array(
					'Key'     => $item['Key'],
					'Message' => $e->getMessage(),
				);
			}
		}

		return $failures;
	}

	/**
	 * Upload file to bucket.
	 *
	 * @param array $args
	 *
	 * @throws Exception
	 */
	public function upload_object( array $args ) {
		if ( ! empty( $args['SourceFile'] ) ) {
			$file = fopen( $args['SourceFile'], 'r' );
		} elseif ( ! empty( $args['Body'] ) ) {
			$file = $args['Body'];
		} else {
			throw new Exception( __METHOD__ . ' called without either "SourceFile" or "Body" arg.' );
		}

		$options = array(
			'name' => $args['Key'],
		);

		if ( ! empty( $args['ACL'] ) ) {
			$options['predefinedAcl'] = $args['ACL'];
		}

		if ( ! empty( $args['ContentType'] ) ) {
			$options['metadata']['contentType'] = $args['ContentType'];
		}

		if ( ! empty( $args['CacheControl'] ) ) {
			$options['metadata']['cacheControl'] = $args['CacheControl'];
		}

		// TODO: Potentially strip out known keys from $args and then put rest in $options['metadata']['metadata'].

		$object = $this->storage->bucket( $args['Bucket'] )->upload( $file, $options ); // phpcs:ignore
	}

	/**
	 * Delete object from bucket.
	 *
	 * @param array $args
	 */
	public function delete_object( array $args ) {
		$this->storage->bucket( $args['Bucket'] )->object( $args['Key'] )->delete();
	}

	/**
	 * Delete multiple objects from bucket.
	 *
	 * @param array $args
	 */
	public function delete_objects( array $args ) {
		if ( isset( $args['Delete']['Objects'] ) ) {
			$keys = $args['Delete']['Objects'];
		} elseif ( isset( $args['Objects'] ) ) {
			$keys = $args['Objects'];
		}

		if ( ! empty( $keys ) ) {
			$bucket = $this->storage->bucket( $args['Bucket'] );

			// Unfortunately the GCP PHP SDK does not have batch operations.
			foreach ( $keys as $key ) {
				$bucket->object( $key['Key'] )->delete();
			}
		}
	}

	/**
	 * Returns arrays of found keys for given bucket and prefix locations, retaining given array's integer based index.
	 *
	 * @param array $locations Array with attachment ID as key and Bucket and Prefix in an associative array as values.
	 *
	 * @return array
	 */
	public function list_keys( array $locations ) {
		$keys = array();

		$results = array_map( function ( $location ) {
			return $this->storage->bucket( $location['Bucket'] )->objects( array( 'prefix' => $location['Prefix'], 'fields' => 'items/name' ) );
		}, $locations );

		foreach ( $results as $attachment_id => $objects ) {
			/** @var StorageObject $object */
			foreach ( $objects as $object ) {
				$keys[ $attachment_id ][] = $object->name();
			}
		}

		return $keys;
	}

	/**
	 * Copies objects into current bucket from another bucket hosted with provider.
	 *
	 * @param array $items
	 *
	 * @return array Failures with elements Key and Message
	 */
	public function copy_objects( array $items ) {
		$failures = array();

		// Unfortunately the GCP PHP SDK does not have batch operations.
		foreach ( $items as $item ) {
			list( $bucket, $key ) = explode( '/', urldecode( $item['CopySource'] ), 2 );

			$options = array(
				'name' => $item['Key'],
			);

			if ( ! empty( $item['ACL'] ) ) {
				$options['predefinedAcl'] = $item['ACL'];
			}

			try {
				$this->storage->bucket( $bucket )->object( $key )->copy( $item['Bucket'], $options );
			} catch ( Exception $e ) {
				$failures[] = array(
					'Key'     => $item['Key'],
					'Message' => $e->getMessage(),
				);
			}
		}

		return $failures;
	}

	/**
	 * Generate the stream wrapper protocol
	 *
	 * @param string $region
	 *
	 * @return string
	 */
	protected function get_stream_wrapper_protocol( $region ) {
		$protocol = 'gs';

		// TODO: Determine whether same protocol for all regions is ok.
		// Assumption not as each may have client instance, hence keeping this for time being.
		$protocol .= str_replace( '-', '', $region );

		return $protocol;
	}

	/**
	 * Register a stream wrapper for specific region.
	 *
	 * @param string $region
	 *
	 * @return bool
	 */
	public function register_stream_wrapper( $region ) {
		$protocol = $this->get_stream_wrapper_protocol( $region );

		// Register the region specific stream wrapper to be used by plugins
		GCP_GCS_Stream_Wrapper::register( $this->storage, $protocol );

		return true;
	}

	/**
	 * Check that a bucket and key can be written to.
	 *
	 * @param string $bucket
	 * @param string $key
	 * @param string $file_contents
	 *
	 * @return bool|string Error message on unexpected exception
	 */
	public function can_write( $bucket, $key, $file_contents ) {
		try {
			// Attempt to create the test file.
			$this->upload_object( array(
				'Bucket' => $bucket,
				'Key'    => $key,
				'Body'   => $file_contents,
			) );

			// delete it straight away if created
			$this->delete_object( array(
				'Bucket' => $bucket,
				'Key'    => $key,
			) );

			return true;
		} catch ( Exception $e ) {
			// If we encounter an error that isn't from Google, throw that error.
			if ( ! $e instanceof GoogleException ) {
				return $e->getMessage();
			}
		}

		return false;
	}

	/**
	 * Get the region specific prefix for raw URL
	 *
	 * @param string   $region
	 * @param null|int $expires
	 *
	 * @return string
	 */
	protected function url_prefix( $region = '', $expires = null ) {
		return '';
	}

	/**
	 * Get the url domain for the files
	 *
	 * @param string $domain Likely prefixed with region
	 * @param string $bucket
	 * @param string $region
	 * @param int    $expires
	 * @param array  $args   Allows you to specify custom URL settings
	 *
	 * @return string
	 */
	protected function url_domain( $domain, $bucket, $region = '', $expires = null, $args = array() ) {
		if ( apply_filters( 'as3cf_' . static::get_provider_key_name() . '_' . static::get_service_key_name() . '_bucket_in_path', false !== strpos( $bucket, '.' ) ) ) {
			$domain = $domain . '/' . $bucket;
		} else {
			// TODO: Is this mode allowed for GCS native URLs?
			$domain = $bucket . '.' . $domain;
		}

		return $domain;
	}

	/**
	 * Get the suffix param to append to the link to the provider's console.
	 *
	 * @param string $bucket
	 * @param string $prefix
	 * @param string $region
	 *
	 * @return string
	 */
	protected function get_console_url_suffix_param( string $bucket = '', string $prefix = '', string $region = '' ): string {
		if ( ! empty( $this->get_project_id() ) ) {
			return '?project=' . $this->get_project_id();
		}

		return '';
	}

	/**
	 * Get the Project ID for the current client.
	 *
	 * @return string|null
	 */
	private function get_project_id() {
		static $project_id = null;

		// If not already grabbed, get project id from key file data but only if client properly instantiated.
		if ( null === $project_id && ! empty( $this->storage ) && $this->use_key_file() ) {
			$key_file_path = $this->get_key_file_path();

			if ( ! empty( $key_file_path ) && file_exists( $key_file_path ) ) {
				$key_file_contents = json_decode( file_get_contents( $key_file_path ), true );

				if ( ! empty( $key_file_contents['project_id'] ) ) {
					$project_id = $key_file_contents['project_id'];

					return $project_id;
				}
			}

			$key_file_contents = $this->get_key_file();

			if ( is_array( $key_file_contents ) && ! empty( $key_file_contents['project_id'] ) ) {
				$project_id = $key_file_contents['project_id'];

				return $project_id;
			}
		}

		return $project_id;
	}

	/**
	 * Read key file contents from path and convert it to the appropriate format for this provider.
	 *
	 * @param string $path
	 *
	 * @return mixed
	 */
	protected function get_key_file_path_contents( string $path ) {
		$notice_id   = 'validate-key-file-path';
		$notice_args = array(
			'type'                  => 'error',
			'only_show_in_settings' => true,
			'only_show_on_tab'      => 'media',
			'hide_on_parent'        => true,
			'custom_id'             => $notice_id,
		);

		$content = json_decode( file_get_contents( $path ), true );

		if ( empty( $content ) ) {
			$this->as3cf->notices->add_notice( __( 'Media cannot be offloaded due to invalid JSON in the key file.', 'amazon-s3-and-cloudfront' ), $notice_args );

			return false;
		}

		return $content;
	}

	/**
	 * Google specific validation of the key file contents.
	 *
	 * @param array $key_file_content
	 *
	 * @return bool
	 */
	public function validate_key_file_content( $key_file_content ): bool {
		$notice_id = 'validate-key-file-content';
		$this->as3cf->notices->remove_notice_by_id( $notice_id );

		$notice_args = array(
			'type'                  => 'error',
			'only_show_in_settings' => true,
			'only_show_on_tab'      => 'media',
			'hide_on_parent'        => true,
			'custom_id'             => $notice_id,
		);

		if ( ! isset( $key_file_content['project_id'] ) ) {
			$this->as3cf->notices->add_notice(
				sprintf(
					__( 'Media cannot be offloaded due to a missing <code>project_id</code> field which may be the result of an old or obsolete key file. <a href="%1$s" target="_blank">Create a new key file</a>', 'amazon-s3-and-cloudfront' ),
					static::get_provider_service_quick_start_url() . '#service-account-key-file'
				),
				$notice_args
			);

			return false;
		}

		if ( ! isset( $key_file_content['private_key'] ) ) {
			$this->as3cf->notices->add_notice(
				sprintf(
					__( 'Media cannot be offloaded due to a missing <code>private_key</code> field in the key file. <a href="%1$s" target="_blank"">Create a new key file</a>', 'amazon-s3-and-cloudfront' ),
					static::get_provider_service_quick_start_url() . '#service-account-key-file'
				),
				$notice_args
			);

			return false;
		}

		if ( ! isset( $key_file_content['type'] ) ) {
			$this->as3cf->notices->add_notice(
				sprintf(
					__( 'Media cannot be offloaded due to a missing <code>type</code> field in the key file. <a href="%1$s" target="_blank">Create a new key file</a>', 'amazon-s3-and-cloudfront' ),
					static::get_provider_service_quick_start_url() . '#service-account-key-file'
				),
				$notice_args
			);

			return false;
		}

		if ( ! isset( $key_file_content['client_email'] ) ) {
			$this->as3cf->notices->add_notice(
				sprintf(
					__( 'Media cannot be offloaded due to a missing <code>client_email</code> field in the key file. <a href="%1$s" target="_blank">Create a new key file</a>', 'amazon-s3-and-cloudfront' ),
					static::get_provider_service_quick_start_url() . '#service-account-key-file'
				),
				$notice_args
			);

			return false;
		}

		return true;
	}

	/**
	 * Prepare the bucket error.
	 *
	 * @param WP_Error $object
	 * @param bool     $single Are we dealing with a single bucket?
	 *
	 * @return string
	 */
	public function prepare_bucket_error( WP_Error $object, bool $single = true ): string {
		if ( false !== strpos( $object->get_error_message(), "OpenSSL unable to sign" ) ) {
			return sprintf(
				__( 'Media cannot be offloaded due to an invalid OpenSSL Private Key. <a href="%1$s" target="_blank">Update the key file</a>', 'amazon-s3-and-cloudfront' ),
				static::get_provider_service_quick_start_url() . '#service-account-key-file'
			);
		}

		// This may be a JSON error message from Google.
		$message = json_decode( $object->get_error_message() );
		if ( ! is_null( $message ) ) {
			if ( isset( $message->error ) && 'invalid_grant' === $message->error ) {
				return sprintf(
					__( 'Media cannot be offloaded using the provided service account. <a href="%1$s" target="_blank">Read more</a>', 'amazon-s3-and-cloudfront' ),
					static::get_provider_service_quick_start_url() . '#service-account-key-file'
				);
			}

			if ( isset( $message->error->code ) && 404 === $message->error->code ) {
				return sprintf(
					__( 'Media cannot be offloaded because a bucket with the configured name does not exist. <a href="%1$s">Enter a different bucket</a>', 'amazon-s3-and-cloudfront' ),
					'#/storage/bucket'
				);
			}
		}

		// Fallback to generic error parsing.
		return parent::prepare_bucket_error( $object, $single );
	}
}
