<?php

namespace DeliciousBrains\WP_Offload_Media\Integrations;

use AS3CF_Error;
use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;
use DeliciousBrains\WP_Offload_Media\Items\Remove_Local_Handler;
use Exception;
use WP_Error;

class Advanced_Custom_Fields extends Integration {
	/**
	 * Is installed?
	 *
	 * @return bool
	 */
	public static function is_installed(): bool {
		if ( class_exists( 'acf' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Init integration.
	 */
	public function init() {
		// Nothing to do.
	}

	/**
	 * @inheritDoc
	 */
	public function setup() {
		/*
		 * Content Filtering
		 */
		add_filter( 'acf/load_value/type=text', array( $this->as3cf->filter_local, 'filter_post' ) );
		add_filter( 'acf/load_value/type=textarea', array( $this->as3cf->filter_local, 'filter_post' ) );
		add_filter( 'acf/load_value/type=wysiwyg', array( $this->as3cf->filter_local, 'filter_post' ) );
		add_filter( 'acf/update_value/type=text', array( $this->as3cf->filter_provider, 'filter_post' ) );
		add_filter( 'acf/update_value/type=textarea', array( $this->as3cf->filter_provider, 'filter_post' ) );
		add_filter( 'acf/update_value/type=wysiwyg', array( $this->as3cf->filter_provider, 'filter_post' ) );

		/*
		 * Image Crop Add-on
		 * https://en-gb.wordpress.org/plugins/acf-image-crop-add-on/
		 */
		if ( class_exists( 'acf_field_image_crop' ) ) {
			add_filter( 'wp_get_attachment_metadata', array( $this, 'download_image' ), 10, 2 );
			add_filter( 'sanitize_file_name', array( $this, 'remove_original_after_download' ) );
		}

		/*
		 * Rewrite URLs in field and field group config.
		 */
		add_filter( 'acf/load_fields', array( $this, 'acf_load_config' ), 10, 1 );
		add_filter( 'acf/load_field_group', array( $this, 'acf_load_config' ), 10, 1 );
	}

	/**
	 * Copy back the S3 image for cropping
	 *
	 * @param array $data
	 * @param int   $post_id
	 *
	 * @return array
	 */
	public function download_image( $data, $post_id ) {
		$this->maybe_download_image( $post_id );

		return $data;
	}

	/**
	 * Copy back the S3 image
	 *
	 * @param int $post_id
	 *
	 * @return bool|WP_Error
	 */
	public function maybe_download_image( $post_id ) {
		if ( false === $this->as3cf->plugin_compat->maybe_process_on_action( 'acf_image_crop_perform_crop', true ) ) {
			return $this->as3cf->_throw_error( 1, 'Not ACF crop process' );
		}

		$file = get_attached_file( $post_id, true );

		if ( file_exists( $file ) ) {
			return $this->as3cf->_throw_error( 2, 'File already exists' );
		}

		$as3cf_item = Media_Library_Item::get_by_source_id( $post_id );

		if ( ! $as3cf_item ) {
			return $this->as3cf->_throw_error( 3, 'Attachment not offloaded' );
		}

		$callers = debug_backtrace(); // phpcs:ignore
		foreach ( $callers as $caller ) {
			if ( isset( $caller['function'] ) && 'image_downsize' === $caller['function'] ) {
				// Don't copy when downsizing the image, which would result in bringing back
				// the newly cropped image from S3.
				return $this->as3cf->_throw_error( 4, 'Copying back cropped file' );
			}
		}

		// Copy back the original file for cropping
		$result = $this->as3cf->plugin_compat->copy_provider_file_to_server( $as3cf_item, $file );

		if ( false === $result ) {
			return $this->as3cf->_throw_error( 5, 'Copy back failed' );
		}

		// Mark the attachment so we know to remove it later after the crop
		update_post_meta( $post_id, 'as3cf_acf_cropped_to_remove', true );

		return true;
	}

	/**
	 * Remove the original image downloaded for the cropping after it has been processed
	 *
	 * @param string $filename
	 *
	 * @return mixed
	 */
	public function remove_original_after_download( $filename ) {
		$this->maybe_remove_original_after_download();

		return $filename;
	}

	/**
	 * Remove the original image from the server
	 *
	 * @return bool|WP_Error
	 */
	public function maybe_remove_original_after_download() {
		if ( false === $this->as3cf->plugin_compat->maybe_process_on_action( 'acf_image_crop_perform_crop', true ) ) {
			return $this->as3cf->_throw_error( 1, 'Not ACF crop process' );
		}

		$original_attachment_id = $this->as3cf->filter_input( 'id', INPUT_POST, FILTER_VALIDATE_INT );

		if ( ! isset( $original_attachment_id ) ) {
			// Can't find the original attachment id
			return $this->as3cf->_throw_error( 6, 'Attachment ID not available' );
		}

		$as3cf_item = Media_Library_Item::get_by_source_id( $original_attachment_id );

		if ( ! $as3cf_item ) {
			// Original attachment not on S3
			return $this->as3cf->_throw_error( 3, 'Attachment not offloaded' );
		}

		if ( ! get_post_meta( $original_attachment_id, 'as3cf_acf_cropped_to_remove', true ) ) {
			// Original attachment should exist locally, no need to delete
			return $this->as3cf->_throw_error( 7, 'Attachment not to be removed from server' );
		}

		// Remove the original file from the server
		$original_file = get_attached_file( $original_attachment_id, true );

		$remove_local_handler = $this->as3cf->get_item_handler( Remove_Local_Handler::get_item_handler_key_name() );
		$remove_local_handler->handle( $as3cf_item, array( 'files_to_remove' => array( $original_file ) ) );

		// Remove marker
		delete_post_meta( $original_attachment_id, 'as3cf_acf_cropped_to_remove' );

		return true;
	}

	/**
	 * Rewrites URLs from local to remote inside ACF field and field group config. If the
	 * rewriting process fails, it will return the original config.
	 *
	 * @handles acf/load_fields
	 * @handles acf/load_field_group
	 *
	 * @param array $config
	 *
	 * @return array
	 */
	public function acf_load_config( array $config ): array {
		try {
			$filtered_config = unserialize( $this->as3cf->filter_local->filter_post( serialize( $config ) ) );
		} catch ( Exception $e ) {
			AS3CF_Error::log( __METHOD__ . ' ' . $e->getMessage() );

			return $config;
		}

		return is_array( $filtered_config ) ? $filtered_config : $config;
	}
}
