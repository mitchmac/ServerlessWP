<?php

namespace DeliciousBrains\WP_Offload_Media\Providers\Storage;

use AS3CF_Error;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\CommandPool;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\ResultInterface;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\S3\Exception\S3Exception;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\S3\S3Client;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\Sdk;
use DeliciousBrains\WP_Offload_Media\Providers\Storage\Streams\AWS_S3_Stream_Wrapper;
use Exception;
use WP_Error;

class AWS_Provider extends Storage_Provider {

	/**
	 * @var Sdk
	 */
	private $aws_client;

	/**
	 * @var S3Client
	 */
	private $s3_client;

	/**
	 * @var string
	 */
	protected static $provider_name = 'Amazon Web Services';

	/**
	 * @var string
	 */
	protected static $provider_short_name = 'AWS';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $provider_key_name = 'aws';

	/**
	 * @var string
	 */
	protected static $service_name = 'Simple Storage Solution';

	/**
	 * @var string
	 */
	protected static $service_short_name = 'S3';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $service_key_name = 's3';

	/**
	 * Optional override of "Provider Name" + "Service Name" for friendly name for service.
	 *
	 * @var string
	 */
	protected static $provider_service_name = 'Amazon S3';

	/**
	 * The slug for the service's quick start guide doc.
	 *
	 * @var string
	 */
	protected static $provider_service_quick_start_slug = 'amazon-s3-quick-start-guide';

	/**
	 * @var array
	 */
	protected static $access_key_id_constants = array(
		'AS3CF_AWS_ACCESS_KEY_ID',
		'DBI_AWS_ACCESS_KEY_ID',
		'AWS_ACCESS_KEY_ID',
	);

	/**
	 * @var array
	 */
	protected static $secret_access_key_constants = array(
		'AS3CF_AWS_SECRET_ACCESS_KEY',
		'DBI_AWS_SECRET_ACCESS_KEY',
		'AWS_SECRET_ACCESS_KEY',
	);

	/**
	 * @var array
	 */
	protected static $use_server_roles_constants = array(
		'AS3CF_AWS_USE_EC2_IAM_ROLE',
		'DBI_AWS_USE_EC2_IAM_ROLE',
		'AWS_USE_EC2_IAM_ROLE',
	);

	/**
	 * @var bool
	 */
	protected static $block_public_access_supported = true;

	/**
	 * @var bool
	 */
	protected static $object_ownership_supported = true;

	/**
	 * @var array
	 */
	protected static $regions = array(
		'us-east-1'      => 'US East (N. Virginia)',
		'us-east-2'      => 'US East (Ohio)',
		'us-west-1'      => 'US West (N. California)',
		'us-west-2'      => 'US West (Oregon)',
		'ca-central-1'   => 'Canada (Central)',
		'af-south-1'     => 'Africa (Cape Town)',
		'ap-east-1'      => 'Asia Pacific (Hong Kong)',
		'ap-south-1'     => 'Asia Pacific (Mumbai)',
		'ap-south-2'     => 'Asia Pacific (Hyderabad)',
		'ap-northeast-2' => 'Asia Pacific (Seoul)',
		'ap-northeast-3' => 'Asia Pacific (Osaka)',
		'ap-southeast-1' => 'Asia Pacific (Singapore)',
		'ap-southeast-2' => 'Asia Pacific (Sydney)',
		'ap-southeast-3' => 'Asia Pacific (Jakarta)',
		'ap-northeast-1' => 'Asia Pacific (Tokyo)',
		'cn-north-1'     => 'China (Beijing)',
		'cn-northwest-1' => 'China (Ningxia)',
		'eu-central-1'   => 'EU (Frankfurt)',
		'eu-central-2'   => 'EU (Zurich)',
		'eu-west-1'      => 'EU (Ireland)',
		'eu-west-2'      => 'EU (London)',
		'eu-south-1'     => 'EU (Milan)',
		'eu-south-2'     => 'EU (Spain)',
		'eu-west-3'      => 'EU (Paris)',
		'eu-north-1'     => 'EU (Stockholm)',
		'me-south-1'     => 'Middle East (Bahrain)',
		'me-central-1'   => 'Middle East (UAE)',
		'sa-east-1'      => 'South America (São Paulo)',
	);

	/**
	 * @var string
	 */
	protected static $default_region = 'us-east-1';

	/**
	 * @var string
	 */
	protected $default_domain = 'amazonaws.com';

	/**
	 * @var string
	 */
	protected $console_url = 'https://console.aws.amazon.com/s3/buckets/';

	/**
	 * @var string
	 */
	protected $console_url_prefix_param = '/';

	const API_VERSION       = '2006-03-01';
	const SIGNATURE_VERSION = 'v4';

	const PUBLIC_ACL  = 'public-read';
	const PRIVATE_ACL = 'private';

	/**
	 * Keeps track of Block Public Access state for buckets to save hitting API.
	 *
	 * @var array
	 */
	private $blocked_buckets = array();

	/**
	 * Keeps track of Object Ownership Enforced state for buckets to save hitting API.
	 *
	 * @var array
	 */
	private $enforced_ownership_buckets = array();

	/**
	 * AWS_Provider constructor.
	 *
	 * @param \AS3CF_Plugin_Base $as3cf
	 */
	public function __construct( \AS3CF_Plugin_Base $as3cf ) {
		parent::__construct( $as3cf );

		// Autoloader.
		require_once $as3cf->get_plugin_sdks_dir_path() . '/Aws3/aws-autoloader.php';

		if ( ! function_exists( 'idn_to_ascii' ) && ! defined( 'IDNA_DEFAULT' ) ) {
			define( 'IDNA_DEFAULT', 0 );
		}
	}

	/**
	 * Run a command over a batch of items, returning any failures.
	 *
	 * @param string $command
	 * @param array  $items
	 *
	 * @return array Failures with elements Key and Message
	 *
	 * NOTE: Only really useful for commands that take 'Key' as one of their args.
	 */
	private function batch_command( $command, array $items ) {
		$failures = array();

		$commands = array_map( function ( $item ) use ( $command ) {
			return $this->s3_client->getCommand( $command, $item );
		}, $items );

		$results = CommandPool::batch( $this->s3_client, $commands );

		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				/* @var S3Exception $result */
				if ( is_a( $result, 'DeliciousBrains\WP_Offload_Media\Aws3\Aws\S3\Exception\S3Exception' ) ) {
					$failed_command = $result->getCommand()->toArray();
					$failures[]     = array(
						'Key'     => $failed_command['Key'],
						'Message' => $result->getAwsErrorMessage(),
					);
				}
			}
		}

		return $failures;
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
		if ( false !== strpos( $object->get_error_message(), 'InvalidAccessKeyId' ) ) {
			return sprintf(
				__( 'Media cannot be offloaded due to an invalid Access Key ID. <a href="%1$s">Update access keys</a>', 'amazon-s3-and-cloudfront' ),
				'#/storage/provider'
			);
		}

		if ( false !== strpos( $object->get_error_message(), 'SignatureDoesNotMatch' ) ) {
			return sprintf(
				__( 'Media cannot be offloaded due to an invalid Secret Access Key. <a href="%1$s">Update access keys</a>', 'amazon-s3-and-cloudfront' ),
				'#/storage/provider'
			);
		}

		if (
			false !== strpos( $object->get_error_message(), 'CreateBucket' ) &&
			false !== strpos( $object->get_error_message(), 'BucketAlreadyOwnedByYou' )
		) {
			return __( 'The bucket name already exists in your account. To confirm you\'d like to use it, please select "Use Existing Bucket". Alternatively, enter a different bucket name.', 'amazon-s3-and-cloudfront' );
		}

		if (
			false !== strpos( $object->get_error_message(), 'CreateBucket' ) &&
			false !== strpos( $object->get_error_message(), 'BucketAlreadyExists' )
		) {
			return __( 'The bucket name already exists in another account. Please enter a different bucket name.', 'amazon-s3-and-cloudfront' );
		}

		if (
			false !== strpos( $object->get_error_message(), 'GetBucketLocation' ) &&
			false !== strpos( $object->get_error_message(), 'NoSuchBucket' )
		) {
			return sprintf(
				__( 'Media cannot be offloaded because a bucket with the configured name does not exist. <a href="%1$s">Enter a different bucket</a>', 'amazon-s3-and-cloudfront' ),
				'#/storage/bucket'
			);
		}

		if (
			false !== strpos( $object->get_error_message(), 'GetBucketLocation' ) &&
			false !== strpos( $object->get_error_message(), 'InvalidBucketName' )
		) {
			return sprintf(
				__( 'Media cannot be offloaded because the bucket name is not valid. <a href="%1$s">Enter a different bucket name</a>.', 'amazon-s3-and-cloudfront' ),
				'#/storage/bucket'
			);
		}

		// Fallback to generic error parsing.
		return parent::prepare_bucket_error( $object, $single );
	}

	/**
	 * Returns default args array for the client.
	 *
	 * @return array
	 */
	protected function default_client_args() {
		return array(
			'signature_version'              => static::SIGNATURE_VERSION,
			'version'                        => static::API_VERSION,
			'region'                         => static::get_default_region(),
			'csm'                            => ! apply_filters( 'as3cf_disable_aws_csm', true ),
			'use_arn_region'                 => ! apply_filters( 'as3cf_disable_aws_use_arn_region', true ),
			's3_us_east_1_regional_endpoint' => apply_filters( 'as3cf_aws_s3_us_east_1_regional_endpoint', 'legacy' ),
			'endpoint_discovery'             => apply_filters( 'as3cf_disable_aws_endpoint_discovery', true ) ? array( 'enabled' => false ) : array( 'enabled' => true ),
			'sts_regional_endpoints'         => apply_filters( 'as3cf_aws_sts_regional_endpoints', 'legacy' ),
			'use_aws_shared_config_files'    => apply_filters( 'as3cf_aws_use_shared_config_files', false ),
		);
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
		$this->aws_client = new Sdk( $args );
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
	 * @return S3Client
	 */
	protected function init_service_client( array $args = array() ) {
		if ( empty( $args['region'] ) || $args['region'] === static::get_default_region() ) {
			$this->s3_client = $this->aws_client->createMultiRegionS3( $args );
		} else {
			$this->s3_client = $this->aws_client->createS3( $args );
		}

		return $this->s3_client;
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

		$region = strtolower( $region );

		/**
		 * Translate older bucket locations to newer S3 region names
		 * http://docs.aws.amazon.com/general/latest/gr/rande.html#s3_region
		 */
		switch ( $region ) {
			case 'eu':
				$region = 'eu-west-1';
				break;
		}

		return $region;
	}

	/**
	 * Create bucket.
	 *
	 * @param array $args
	 */
	public function create_bucket( array $args ) {
		if ( ! empty( $args['LocationConstraint'] ) ) {
			$args['CreateBucketConfiguration']['LocationConstraint'] = $args['LocationConstraint'];
			unset( $args['LocationConstraint'] );
		}

		$this->s3_client->createBucket( $args );

		if ( static::block_public_access_supported() ) {
			$this->block_public_access( $args['Bucket'], false );
		}

		if ( static::object_ownership_supported() ) {
			$this->enforce_object_ownership( $args['Bucket'], false );
		}
	}

	/**
	 * Check whether bucket exists.
	 *
	 * @param string $bucket
	 *
	 * @return bool
	 */
	public function does_bucket_exist( $bucket ) {
		return $this->s3_client->doesBucketExist( $bucket );
	}

	/**
	 * Returns region for bucket.
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	public function get_bucket_location( array $args ) {
		$location = $this->s3_client->getBucketLocation( $args );
		$region   = empty( $location['LocationConstraint'] ) ? '' : $location['LocationConstraint'];

		return $region;
	}

	/**
	 * List buckets.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function list_buckets( array $args = array() ) {
		return $this->s3_client->listBuckets( $args )->toArray();
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
		return $this->s3_client->doesObjectExist( $bucket, $key, $options );
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
	 * Is public access to the given bucket prohibited?
	 *
	 * @param string $bucket
	 *
	 * @return bool|null
	 *
	 * Note: It's very possible that permissions don't allow the check, in which case default value is returned.
	 */
	public function public_access_blocked( $bucket ) {
		if ( isset( $this->blocked_buckets[ $bucket ] ) ) {
			return $this->blocked_buckets[ $bucket ];
		}

		$blocked = parent::public_access_blocked( $bucket );

		try {
			$result = $this->s3_client->getPublicAccessBlock( array( 'Bucket' => $bucket ) );
		} catch ( Exception $e ) {
			// No policy defined at either bucket or account level, so no blocking happening.
			if ( false !== strpos( $e->getMessage(), 'NoSuchPublicAccessBlockConfiguration' ) ) {
				$blocked = false;
			} else {
				AS3CF_Error::log( 'Could not get Block All Public Access status: ' . $e->getMessage() );

				return $blocked;
			}
		}

		if ( ! empty( $result ) && ! empty( $result['PublicAccessBlockConfiguration'] ) ) {
			$settings = $result['PublicAccessBlockConfiguration'];
			if (
				empty( $settings['BlockPublicAcls'] ) &&
				empty( $settings['BlockPublicPolicy'] ) &&
				empty( $settings['IgnorePublicAcls'] ) &&
				empty( $settings['RestrictPublicBuckets'] ) ) {
				$blocked = false;
			} else {
				$blocked = true;
			}
		}

		$this->blocked_buckets[ $bucket ] = $blocked;

		return $blocked;
	}

	/**
	 * Update the block public access setting for the given bucket.
	 *
	 * @param string $bucket
	 * @param bool   $block
	 */
	public function block_public_access( string $bucket, bool $block ) {
		if ( empty( $bucket ) ) {
			return;
		}

		$setting = array(
			'Bucket'                         => $bucket,
			'PublicAccessBlockConfiguration' => array(
				'BlockPublicAcls'       => $block,
				'BlockPublicPolicy'     => $block,
				'IgnorePublicAcls'      => $block,
				'RestrictPublicBuckets' => $block,
			),
		);

		$this->s3_client->putPublicAccessBlock( $setting );

		unset( $this->blocked_buckets[ $bucket ] );
	}

	/**
	 * Is object ownership enforced (and therefore ACLs disabled)?
	 *
	 * @param string $bucket
	 *
	 * @return bool|null
	 *
	 * Note: It's very possible that permissions don't allow the check, in which case default value is returned.
	 */
	public function object_ownership_enforced( string $bucket ): ?bool {
		if ( isset( $this->enforced_ownership_buckets[ $bucket ] ) ) {
			return $this->enforced_ownership_buckets[ $bucket ];
		}

		$enforced = parent::object_ownership_enforced( $bucket );

		try {
			$result             = $this->s3_client->getBucketOwnershipControls( array( 'Bucket' => $bucket ) );
			$ownership_controls = $result->get( 'OwnershipControls' );
		} catch ( Exception $e ) {
			// No policy defined at either bucket or account level, so no object ownership enforcement happening.
			if ( false !== strpos( $e->getMessage(), 'OwnershipControlsNotFoundError' ) ) {
				$enforced = false;
			} else {
				AS3CF_Error::log( 'Could not get Object Ownership status: ' . $e->getMessage() );

				return $enforced;
			}
		}

		if ( ! empty( $ownership_controls ) && ! empty( $ownership_controls['Rules'][0]['ObjectOwnership'] ) ) {
			if ( 'BucketOwnerEnforced' === $ownership_controls['Rules'][0]['ObjectOwnership'] ) {
				$enforced = true;
			} else {
				$enforced = false;
			}
		}

		$this->enforced_ownership_buckets[ $bucket ] = $enforced;

		return $enforced;
	}

	/**
	 * Update the object ownership enforced setting for the given bucket.
	 *
	 * @param string $bucket
	 * @param bool   $enforce
	 */
	public function enforce_object_ownership( string $bucket, bool $enforce ) {
		if ( empty( $bucket ) ) {
			return;
		}

		$ownership = $enforce ? 'BucketOwnerEnforced' : 'BucketOwnerPreferred';

		$setting = array(
			'Bucket'            => $bucket,
			'OwnershipControls' => array(
				'Rules' => array(
					array(
						'ObjectOwnership' => $ownership,
					),
				),
			),
		);

		$this->s3_client->putBucketOwnershipControls( $setting );

		unset( $this->enforced_ownership_buckets[ $bucket ] );
	}

	/**
	 * Download object, destination specified in args.
	 *
	 * @see https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-s3-2006-03-01.html#getobject
	 *
	 * @param array $args
	 */
	public function get_object( array $args ) {
		$this->s3_client->getObject( $args );
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
		$command_args = array( 'Bucket' => $bucket, 'Key' => $key );

		if ( ! empty( $args ) ) {
			$command_args = array_merge( $command_args, $args );
		}

		$command = $this->s3_client->getCommand( 'GetObject', $command_args );

		if ( empty( $timestamp ) || ! is_int( $timestamp ) || $timestamp < 0 ) {
			return (string) \DeliciousBrains\WP_Offload_Media\Aws3\Aws\serialize( $command )->getUri();
		} else {
			return (string) $this->s3_client->createPresignedRequest( $command, $timestamp )->getUri();
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
		return $this->s3_client->listObjects( $args )->toArray();
	}

	/**
	 * Update the ACL for an object.
	 *
	 * @param array $args
	 */
	public function update_object_acl( array $args ) {
		$this->s3_client->putObjectAcl( $args );
	}

	/**
	 * Update the ACL for multiple objects.
	 *
	 * @param array $items
	 *
	 * @return array Failures with elements Key and Message
	 */
	public function update_object_acls( array $items ) {
		return $this->batch_command( 'PutObjectAcl', $items );
	}

	/**
	 * Upload file to bucket.
	 *
	 * @param array $args
	 */
	public function upload_object( array $args ) {
		$this->s3_client->putObject( $args );
	}

	/**
	 * Delete object from bucket.
	 *
	 * @param array $args
	 */
	public function delete_object( array $args ) {
		$this->s3_client->deleteObject( $args );
	}

	/**
	 * Delete multiple objects from bucket.
	 *
	 * @param array $args
	 */
	public function delete_objects( array $args ) {
		if ( ! isset( $args['Delete'] ) && isset( $args['Objects'] ) ) {
			$args['Delete']['Objects'] = $args['Objects'];
			unset( $args['Objects'] );
		}

		$this->s3_client->deleteObjects( $args );
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

		$commands = array_map( function ( $location ) {
			return $this->s3_client->getCommand( 'ListObjects', $location );
		}, $locations );

		$results = CommandPool::batch( $this->s3_client, $commands, array( 'preserve_iterator_keys' => true ) );

		/** @var ResultInterface $result */
		foreach ( $results as $attachment_id => $result ) {
			if ( is_a( $result, 'DeliciousBrains\WP_Offload_Media\Aws3\Aws\ResultInterface' ) ) {
				$found_keys = $result->search( 'Contents[].Key' );

				if ( ! empty( $found_keys ) ) {
					$keys[ $attachment_id ] = $found_keys;
				}
			} elseif ( is_a( $result, 'DeliciousBrains\WP_Offload_Media\Aws3\Aws\S3\Exception\S3Exception' ) ) {
				/** @var S3Exception $result */
				AS3CF_Error::log( __FUNCTION__ . ' - ' . $result->getAwsErrorMessage() . ' - Attachment ID: ' . $attachment_id );
			} else {
				AS3CF_Error::log( __FUNCTION__ . ' - Unrecognised class returned from CommandPool::batch - Attachment ID: ' . $attachment_id );
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
		return $this->batch_command( 'CopyObject', $items );
	}

	/**
	 * Generate the stream wrapper protocol
	 *
	 * @param string $region
	 *
	 * @return string
	 */
	protected function get_stream_wrapper_protocol( $region ) {
		$protocol = 's3';
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

		// Register the region specific S3 stream wrapper to be used by plugins
		AWS_S3_Stream_Wrapper::register( $this->s3_client, $protocol );

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
			// If we encounter an error that isn't access denied, throw that error.
			if ( ! $e instanceof S3Exception || ! in_array( $e->getAwsErrorCode(), array( 'AccessDenied', 'NoSuchBucket' ) ) ) {
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
		$prefix = 's3';

		if ( ! empty( $region ) && $this->get_default_region() !== $region ) {
			$prefix .= '.' . $region;
		}

		return $prefix;
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
			// TODO: This mode is going away, kinda, sorta, one day.
			// TODO: When AWS sort out HTTPS for bucket in domain with dots we can remove this format.
			// @see https://aws.amazon.com/blogs/aws/amazon-s3-path-deprecation-plan-the-rest-of-the-story/
			$domain = $domain . '/' . $bucket;
		} else {
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
		return empty( $region ) ? '' : '?region=' . $region;
	}

	/**
	 * Notification strings for when Block All Public Access enabled on storage provider but delivery provider does not support it.
	 *
	 * @return array Keys are heading and message
	 */
	public static function get_block_public_access_warning() {
		global $as3cf;

		$cloudfront_setup_doc = $as3cf::dbrains_url(
			'/wp-offload-media/doc/cloudfront-setup/',
			array( 'utm_campaign' => 'support+docs', 'utm_content' => 'change+bucket+access' )
		);

		$bucket_access_blocked_message = sprintf(
			__( 'If you\'re following our documentation on <a href="%1$s">setting up Amazon CloudFront</a> for delivery, you can ignore this warning and continue.<br> If you\'re not planning on using Amazon CloudFront for delivery, you need to <a href="%2$s">disable Block All Public Access</a>.', 'amazon-s3-and-cloudfront' ),
			$cloudfront_setup_doc,
			'#/storage/security'
		);

		return array(
			'heading' => _x( 'Block All Public Access is Enabled', 'warning heading', 'amazon-s3-and-cloudfront' ),
			'message' => $bucket_access_blocked_message,
		);
	}

	/**
	 * Description for when Block All Public Access is enabled and Delivery Provider does not support it.
	 *
	 * @return string
	 */
	public static function get_block_public_access_enabled_unsupported_desc() {
		global $as3cf;

		$mesg = __( 'Block All Public Access should only been enabled when Amazon CloudFront is configured for delivery.', 'amazon-s3-and-cloudfront' );
		$mesg .= '&nbsp;';
		$mesg .= $as3cf::settings_more_info_link( 'bucket', '', 'change+bucket+access' );

		return $mesg;
	}

	/**
	 * Description for when Block All Public Access is enabled and Delivery Provider does not support it.
	 *
	 * @return string
	 */
	public static function get_block_public_access_enabled_unsupported_setup_desc() {
		global $as3cf;

		$cloudfront_setup_doc = $as3cf::dbrains_url(
			'/wp-offload-media/doc/cloudfront-setup/',
			array( 'utm_campaign' => 'support+docs', 'utm_content' => 'change+bucket+access' )
		);
		$bucket_settings_doc  = $as3cf::dbrains_url(
			'/wp-offload-media/doc/settings/',
			array( 'utm_campaign' => 'support+docs', 'utm_content' => 'change+bucket+access' ),
			'bucket'
		);

		return sprintf(
			__( 'If you\'re following our documentation on <a href="%1$s">setting up Amazon CloudFront</a> for delivery, you can ignore this warning and continue. If you\'re not planning on using Amazon CloudFront for delivery, you need to <a href="%2$s">disable Block All Public Access</a>.', 'amazon-s3-and-cloudfront' ),
			$cloudfront_setup_doc,
			$bucket_settings_doc
		);
	}

	/**
	 * Description for when Block All Public Access is disabled and Delivery Provider does not support it.
	 *
	 * @return string
	 */
	public static function get_block_public_access_disabled_unsupported_desc() {
		global $as3cf;

		$mesg = __( 'Since you\'re not using Amazon CloudFront for delivery, we recommend you keep Block All Public Access disabled unless you have a very good reason to enable it.', 'amazon-s3-and-cloudfront' );
		$mesg .= '&nbsp;';
		$mesg .= $as3cf::settings_more_info_link( 'bucket', '', 'change+bucket+access' );

		return $mesg;
	}

	/**
	 * Notification strings for when Object Ownership enforced on storage provider but delivery provider does not support it.
	 *
	 * @return array Keys are heading and message
	 *
	 * This function should be overridden by providers that do support enforced Object Ownership to give more specific message.
	 */
	public static function get_object_ownership_enforced_warning() {
		global $as3cf;

		$cloudfront_setup_doc = $as3cf::dbrains_url(
			'/wp-offload-media/doc/cloudfront-setup/',
			array( 'utm_campaign' => 'support+docs', 'utm_content' => 'change+bucket+access' )
		);

		$bucket_access_blocked_message = sprintf(
			__( 'If you\'re following our documentation on <a href="%1$s">setting up Amazon CloudFront</a> for delivery, you can ignore this warning and continue.<br>If you\'re not planning on using Amazon CloudFront for delivery, you need to <a href="%2$s">turn off Object Ownership enforcement</a>.', 'amazon-s3-and-cloudfront' ),
			$cloudfront_setup_doc,
			'#/storage/security'
		);

		return array(
			'heading' => _x( 'Object Ownership is Enforced', 'warning heading', 'amazon-s3-and-cloudfront' ),
			'message' => $bucket_access_blocked_message,
		);
	}

	/**
	 * Description for when Object Ownership is enforced and Delivery Provider does not support it.
	 *
	 * @return string
	 */
	public static function get_object_ownership_enforced_unsupported_desc(): string {
		global $as3cf;

		$mesg = __( 'Object Ownership should only been enforced when Amazon CloudFront is configured for delivery.', 'amazon-s3-and-cloudfront' );
		$mesg .= '&nbsp;';
		$mesg .= $as3cf::settings_more_info_link( 'bucket', '', 'change+bucket+access' );

		return $mesg;
	}

	/**
	 * Description for when Object Ownership is enforced during initial setup.
	 *
	 * @return string
	 */
	public static function get_object_ownership_enforced_unsupported_setup_desc(): string {
		global $as3cf;

		$cloudfront_setup_doc = $as3cf::dbrains_url(
			'/wp-offload-media/doc/cloudfront-setup/',
			array( 'utm_campaign' => 'support+docs', 'utm_content' => 'change+bucket+access' )
		);

		$object_ownership_doc = $as3cf::dbrains_url(
			'/wp-offload-media/doc/amazon-s3-bucket-object-ownership/',
			array( 'utm_campaign' => 'support+docs', 'utm_content' => 'change+bucket+access' )
		);

		return sprintf(
			__( 'If you\'re following our documentation on <a href="%1$s">setting up Amazon CloudFront</a> for delivery, you can ignore this warning and continue.<br>If you\'re not planning on using Amazon CloudFront for delivery, you need to edit the bucket\'s Object Ownership setting and <a href="%2$s">enable ACLs</a> or add a <a href="%3$s">Bucket Policy</a>.', 'amazon-s3-and-cloudfront' ),
			$cloudfront_setup_doc,
			$object_ownership_doc . '#acls',
			$object_ownership_doc . '#bucket-policy'
		);
	}

	/**
	 * Description for when Object Ownership is not enforced and Delivery Provider does not support it.
	 *
	 * @return string
	 */
	public static function get_object_ownership_not_enforced_unsupported_desc(): string {
		global $as3cf;

		$mesg = __( 'Since you\'re not using Amazon CloudFront for delivery, we recommend you do not enforce Object Ownership unless you have a very good reason to do so.', 'amazon-s3-and-cloudfront' );
		$mesg .= '&nbsp;';
		$mesg .= $as3cf::settings_more_info_link( 'bucket', '', 'change+bucket+access' );

		return $mesg;
	}
}
