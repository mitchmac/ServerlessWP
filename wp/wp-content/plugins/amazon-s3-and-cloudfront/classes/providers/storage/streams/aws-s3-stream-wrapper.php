<?php

namespace DeliciousBrains\WP_Offload_Media\Providers\Storage\Streams;

use DeliciousBrains\WP_Offload_Media\Aws3\Aws\CacheInterface;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\S3\S3ClientInterface;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\S3\StreamWrapper;

class AWS_S3_Stream_Wrapper extends StreamWrapper {

	public static $wrapper;

	/**
	 * Register the 's3://' stream wrapper
	 *
	 * @param S3ClientInterface $client       Client to use with the stream wrapper
	 * @param string            $protocol     Protocol to register as.
	 * @param CacheInterface    $cache        Default cache for the protocol.
	 * @param bool              $v2_existence Whether or not to use V2 bucket and object existence methods
	 */
	public static function register( S3ClientInterface $client, $protocol = 's3', CacheInterface $cache = null, $v2_existence = false ) {
		// Keep a shadow copy of the protocol for use with context options.
		static::$wrapper = $protocol;

		parent::register( $client, $protocol, $cache, $v2_existence );
	}

	/**
	 * Overrides so we don't check for stat on directories
	 *
	 * @param string $path
	 * @param int    $flags
	 *
	 * @return array
	 */
	public function url_stat( $path, $flags ) {
		$extension = pathinfo( $path, PATHINFO_EXTENSION );
		// If the path is a directory then return it as always existing.
		if ( ! $extension ) {
			return array(
				0         => 0,
				'dev'     => 0,
				1         => 0,
				'ino'     => 0,
				2         => 16895,
				'mode'    => 16895,
				3         => 0,
				'nlink'   => 0,
				4         => 0,
				'uid'     => 0,
				5         => 0,
				'gid'     => 0,
				6         => -1,
				'rdev'    => -1,
				7         => 0,
				'size'    => 0,
				8         => 0,
				'atime'   => 0,
				9         => 0,
				'mtime'   => 0,
				10        => 0,
				'ctime'   => 0,
				11        => -1,
				'blksize' => -1,
				12        => -1,
				'blocks'  => -1,
			);
		}

		return parent::url_stat( $path, $flags );
	}

	/**
	 * Override the S3 Put Object arguments
	 *
	 * @return bool
	 */
	public function stream_flush() {
		/** @var \Amazon_S3_And_CloudFront|\Amazon_S3_And_CloudFront_Pro $as3cf */
		global $as3cf;

		if ( $as3cf->get_setting( 'use-bucket-acls' ) ) {
			$context = stream_context_get_default();

			if ( null !== $this->context ) {
				$context = $this->context;
			}

			$options = stream_context_get_options( $context );

			// Set the ACL, usually defaults to public.
			$provider                           = $as3cf->get_storage_provider();
			$options[ static::$wrapper ]['ACL'] = $provider->get_default_acl();

			$options = apply_filters( 'wpos3_stream_flush_params', $options ); // Backwards compatibility
			$options = apply_filters( 'as3cf_stream_flush_params', $options );

			stream_context_set_option( $context, $options );
		}

		return parent::stream_flush();
	}

	/**
	 * Dummy function to stop PHP from throwing a wobbly.
	 *
	 * @param string $path
	 * @param int    $option
	 * @param mixed  $value
	 *
	 * @return bool
	 */
	public function stream_metadata( $path, $option, $value ) {
		return true;
	}
}
