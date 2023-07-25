<?php

namespace DeliciousBrains\WP_Offload_Media\Providers\Storage\Streams;

use DeliciousBrains\WP_Offload_Media\Gcp\Google\Cloud\Storage\StorageClient;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\Cloud\Storage\StreamWrapper;

class GCP_GCS_Stream_Wrapper extends StreamWrapper {

	public static $wrapper;

	/**
	 * Register the 'gs://' stream wrapper
	 *
	 * @param StorageClient $client   Client to use with the stream wrapper
	 * @param string        $protocol Protocol to register as.
	 */
	public static function register( StorageClient $client, $protocol = 'gs' ) {
		// Keep a shadow copy of the protocol for use with context options.
		static::$wrapper = $protocol;

		parent::register( $client, $protocol );
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
}