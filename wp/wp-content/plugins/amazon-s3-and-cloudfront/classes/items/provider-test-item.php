<?php

namespace DeliciousBrains\WP_Offload_Media\Items;

use WP_Error;

class Provider_Test_Item extends Media_Library_Item {
	/**
	 * Source type name.
	 *
	 * @var string
	 */
	protected static $source_type_name = 'Provider Test Item';

	/**
	 * Internal source type identifier.
	 *
	 * @var string
	 */
	protected static $source_type = 'provider-test';

	/**
	 * Overrides the parent implementation to avoid storing anything in the database.
	 *
	 * @param bool $update_duplicates
	 *
	 * @return int|WP_Error
	 */
	public function save( $update_duplicates = true ) {
		return 0;
	}

	/**
	 * Overrides the parent implementation. Return all paths unchanged.
	 *
	 * @param Item  $as3cf_item
	 * @param array $paths
	 *
	 * @return array
	 */
	public function remove_duplicate_paths( Item $as3cf_item, $paths ): array {
		return $paths;
	}
}
