<?php

use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;

if ( ! function_exists( 'as3cf_get_attachment_url' ) ) {
	/**
	 * Get the url of the file from provider, may be a signed expiring URL if associated file is set as private.
	 *
	 * NOTE: Returns false if attachment is not offloaded.
	 *
	 * @param int         $post_id            Post ID of the attachment, required.
	 * @param string|null $size               Size of the image to get, optional.
	 * @param bool        $skip_rewrite_check Always return the URL regardless of the 'Rewrite File URLs' setting, optional, default: false.
	 *
	 * @return string|bool|WP_Error
	 */
	function as3cf_get_attachment_url( $post_id, $size = null, $skip_rewrite_check = false ) {
		return as3cf_get_secure_attachment_url( $post_id, null, $size, $skip_rewrite_check );
	}
}
if ( ! function_exists( 'as3cf_get_secure_attachment_url' ) ) {
	/**
	 * Get the signed expiring url of the file from provider.
	 *
	 * NOTE: Returns false if attachment is not offloaded.
	 *
	 * @param int         $post_id            Post ID of the attachment, required.
	 * @param int|null    $expires            Seconds for the link to live, optional, default: 900 (15 minutes).
	 * @param string|null $size               Size of the image to get, optional.
	 * @param bool        $skip_rewrite_check Always return the URL regardless of the 'Rewrite File URLs' setting, optional, default: false.
	 *
	 * @return string|bool|WP_Error
	 */
	function as3cf_get_secure_attachment_url( $post_id, $expires = 900, $size = null, $skip_rewrite_check = false ) {
		$as3cf_item = Media_Library_Item::get_by_source_id( $post_id );
		if ( ! empty( $as3cf_item ) && ! is_wp_error( $as3cf_item ) && $as3cf_item->served_by_provider( $skip_rewrite_check ) ) {
			return $as3cf_item->get_provider_url( $size, $expires );
		}

		return false;
	}
}