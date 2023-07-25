<?php

namespace DeliciousBrains\WP_Offload_Media\Integrations;

use AS3CF_Error;
use AS3CF_Utils;
use DeliciousBrains\WP_Offload_Media\Items\Item;
use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;
use DeliciousBrains\WP_Offload_Media\Items\Remove_Provider_Handler;
use DeliciousBrains\WP_Offload_Media\Items\Upload_Handler;
use Exception;
use WP_Error;
use WP_Post;

class Media_Library extends Integration {
	/**
	 * Is the current process deleting an attachment?
	 *
	 * @var bool
	 */
	private $deleting_attachment = false;

	/**
	 * Keep track of items that are being updated multiple times in one request. I.e. when WP
	 * calls wp_update_attachment_metadata repeatedly during thumbnail generation
	 *
	 * @var array
	 */
	protected $items_in_progress = array();

	/**
	 * Keep track of items that has been replaced by an edit image operation
	 *
	 * @var array
	 */
	protected $replaced_object_keys = array();

	/**
	 * Keep track of context when rendering media library actions.
	 *
	 * @var string
	 */
	protected $render_context = 'list';

	/**
	 * Init Media Library integration.
	 */
	public function init() {
		Media_Library_Item::init_cache();
	}

	/**
	 * @inheritDoc
	 */
	public function setup() {
		// Filter from WordPress media library handling, plugin needs to be set up
		add_filter( 'wp_unique_filename', array( $this, 'wp_unique_filename' ), 10, 3 );
		add_filter( 'wp_update_attachment_metadata', array( $this, 'wp_update_attachment_metadata' ), 110, 2 );
		add_filter( 'pre_delete_attachment', array( $this, 'pre_delete_attachment' ), 20 );
		add_filter( 'delete_attachment', array( $this, 'delete_attachment' ), 20 );
		add_action( 'delete_post', array( $this, 'delete_post' ) );
		add_filter( 'update_attached_file', array( $this, 'update_attached_file' ), 100, 2 );
		add_filter( 'update_post_metadata', array( $this, 'update_post_metadata' ), 100, 5 );

		// Attachment screens/modals
		add_action( 'load-upload.php', array( $this, 'load_media_assets' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_attachment_assets' ), 11 );
		add_action( 'add_meta_boxes', array( $this, 'attachment_provider_meta_box' ) );

		// AJAX
		add_action( 'wp_ajax_as3cf_get_attachment_provider_details', array( $this, 'ajax_get_attachment_provider_details' ) );

		// Rewriting URLs, doesn't depend on plugin being set up
		add_filter( 'wp_get_attachment_url', array( $this, 'wp_get_attachment_url' ), 99, 2 );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'wp_get_attachment_image_attributes' ), 99, 3 );
		add_filter( 'get_image_tag', array( $this, 'maybe_encode_get_image_tag' ), 99, 6 );
		add_filter( 'wp_get_attachment_image_src', array( $this, 'maybe_encode_wp_get_attachment_image_src' ), 99, 4 );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'maybe_encode_wp_prepare_attachment_for_js' ), 99, 3 );
		add_filter( 'image_get_intermediate_size', array( $this, 'maybe_encode_image_get_intermediate_size' ), 99, 3 );
		add_filter( 'get_attached_file', array( $this, 'get_attached_file' ), 10, 2 );
		add_filter( 'wp_get_original_image_path', array( $this, 'get_attached_file' ), 10, 2 );
		add_filter( 'wp_audio_shortcode', array( $this, 'wp_media_shortcode' ), 100, 5 );
		add_filter( 'wp_video_shortcode', array( $this, 'wp_media_shortcode' ), 100, 5 );

		// Srcset handling
		add_filter( 'wp_image_file_matches_image_meta', array( $this, 'image_file_matches_image_meta' ), 10, 4 );

		// Internal filters and actions
		add_filter( 'as3cf_get_provider_url_for_item_source', array( $this, 'filter_get_provider_url_for_item_source' ), 10, 3 );
		add_filter( 'as3cf_get_local_url_for_item_source', array( $this, 'filter_get_local_url_for_item_source' ), 10, 3 );
		add_filter( 'as3cf_get_size_string_from_url_for_item_source', array( $this, 'get_size_string_from_url_for_item_source' ), 10, 3 );
		add_filter( 'as3cf_get_item_secure_url', array( $this, 'get_item_secure_url' ), 10, 5 );
		add_filter( 'as3cf_get_item_url', array( $this, 'get_item_url' ), 10, 5 );
		add_filter( 'as3cf_remove_local_files', array( $this, 'filter_remove_local_files' ), 10, 3 );
		add_filter( 'as3cf_remove_source_files_from_provider', array( $this, 'filter_remove_source_files_from_provider' ), 10, 3 );
		add_action( 'as3cf_post_upload_item', array( $this, 'post_upload_item' ), 10, 1 );
		add_filter( 'as3cf_pre_handle_item_' . Upload_Handler::get_item_handler_key_name(), array( $this, 'pre_handle_item_upload' ), 10, 3 );
		add_filter( 'as3cf_upload_object_key_as_private', array( $this, 'filter_upload_object_key_as_private' ), 10, 3 );
		add_action( 'as3cf_pre_upload_object', array( $this, 'action_pre_upload_object' ), 10, 2 );
	}

	/**
	 * Is installed?
	 *
	 * @return bool
	 */
	public static function is_installed(): bool {
		return true;
	}

	/**
	 * Handles the upload of the attachment to provider when an attachment is updated.
	 *
	 * @handles wp_update_attachment_metadata
	 *
	 * @param array $data meta data for attachment
	 * @param int   $post_id
	 *
	 * @return array|WP_Error
	 * @throws Exception
	 */
	public function wp_update_attachment_metadata( $data, $post_id ) {
		if ( ! $this->as3cf->is_plugin_setup( true ) ) {
			return $data;
		}

		// Some other filter may already have corrupted $data
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Protect against updates of partially formed metadata since WordPress 5.3.
		// Checks whether new upload currently is expected to have subsizes during upload,
		// and if so, are any of its currently missing sizes part of the set.
		if (
			! empty( $data ) &&
			function_exists( 'wp_get_registered_image_subsizes' ) &&
			function_exists( 'wp_get_missing_image_subsizes' ) &&
			wp_attachment_is_image( $post_id )
		) {

			/**
			 * Plugin compat may require that we wait for wp_generate_attachment_metadata
			 * to be run before proceeding with uploading. I.e. Regenerate Thumbnails requires this.
			 *
			 * @param bool $wait True if we should wait AND generate_attachment_metadata hasn't run yet
			 */
			if ( apply_filters( 'as3cf_wait_for_generate_attachment_metadata', false ) ) {
				return $data;
			}

			// There is no unified way of checking whether subsizes are expected, so we have to duplicate WordPress code here.
			$new_sizes = wp_get_registered_image_subsizes();
			$new_sizes = apply_filters( 'intermediate_image_sizes_advanced', $new_sizes, $data, $post_id );

			// If an image has been rotated, remove original image from metadata so that
			// `wp_get_missing_image_subsizes()` doesn't use non-rotated image for
			// generating missing thumbnail sizes.
			// Also, some images, particularly SVGs, don't create thumbnails but do have
			// metadata for them. At the time `wp_get_missing_image_subsizes()` checks
			// the saved metadata, it isn't there, but we already have it.
			$func = function ( $value, $object_id, $meta_key, $single, $meta_type ) use ( $post_id, $data ) {
				if ( ! empty( $value['image_meta']['orientation'] ) ) {
					unset( $value['original_image'] );
				}
				if ( ! empty( $data['image_meta']['orientation'] ) ) {
					unset( $data['original_image'] );
				}

				if (
					is_null( $value ) &&
					$object_id === $post_id &&
					'_wp_attachment_metadata' === $meta_key &&
					$single &&
					'post' === $meta_type
				) {
					// For some reason the filter is expected return an array of values
					// as if not doing a single record.
					return array( $data );
				}

				return $value;
			};

			add_filter( 'get_post_metadata', $func, 10, 5 );
			$missing_sizes = wp_get_missing_image_subsizes( $post_id );
			remove_filter( 'get_post_metadata', $func );

			// If any registered thumbnails smaller than the original are missing,
			// and current filters still expect those sizes, wait until they're all ready.
			if (
				! empty( $new_sizes ) &&
				! empty( $missing_sizes ) &&
				! empty( array_intersect_key( $missing_sizes, $new_sizes ) )
			) {
				return $data;
			}
		}

		// Is this a new item that we're already started working on in this request?
		if ( ! empty( $this->items_in_progress[ $post_id ] ) ) {
			$as3cf_item = $this->items_in_progress[ $post_id ];
		}

		// Is this an update for an existing item.
		if ( empty( $as3cf_item ) || is_wp_error( $as3cf_item ) ) {
			$as3cf_item = Media_Library_Item::get_by_source_id( $post_id );
		}

		// Abort if not already uploaded to provider and the copy setting is off.
		if ( ! $as3cf_item && ! $this->as3cf->get_setting( 'copy-to-s3' ) ) {
			return $data;
		}

		if ( empty( $as3cf_item ) ) {
			$as3cf_item = null;
		}

		/**
		 * Allows implementors to cancel uploading a Media Library item for any reason.
		 *
		 * This filter is triggered by updates to an attachment's metadata.
		 * To potentially cancel an upload started by any method,
		 * please use the 'as3cf_pre_upload_item' filter.
		 *
		 * @param bool               $cancel     True if the upload should be cancelled
		 * @param array              $data       Array describing the object being uploaded
		 * @param int                $post_id    Attachment's ID
		 * @param Media_Library_Item $as3cf_item The Media Library Item object if previously offloaded
		 *
		 * @see as3cf_pre_upload_item
		 */
		$cancel = apply_filters( 'as3cf_pre_update_attachment_metadata', false, $data, $post_id, $as3cf_item );
		if ( false !== $cancel ) {
			return $data;
		}

		$offloaded_files = array();

		// If we still don't have a valid item, create one from scratch.
		if ( empty( $as3cf_item ) || is_wp_error( $as3cf_item ) ) {
			$as3cf_item = Media_Library_Item::create_from_source_id( $post_id );
		} else {
			$offloaded_files = $as3cf_item->offloaded_files();
		}

		// Did we get a WP_Error?
		if ( is_wp_error( $as3cf_item ) ) {
			AS3CF_Error::Log( $as3cf_item->get_error_message() );

			return $data;
		}

		// Or didn't we get anything at all?
		if ( empty( $as3cf_item ) ) {
			$message = sprintf( __( "Can't create item from media library item %d", 'amazon-s3-and-cloudfront' ), $post_id );
			AS3CF_Error::Log( $message );

			return $data;
		}

		// Update item's expected objects from attachment's new metadata.
		$this->update_item_from_new_metadata( $as3cf_item, $data );

		$this->upload_item( $as3cf_item, $offloaded_files );
		$this->items_in_progress[ $post_id ] = $as3cf_item;

		return $data;
	}

	/**
	 * Upload item.
	 *
	 * @param Media_Library_Item $as3cf_item
	 * @param array              $offloaded_files An array of files previously offloaded for the item.
	 */
	protected function upload_item( Media_Library_Item $as3cf_item, array $offloaded_files ) {
		$upload_handler = $this->as3cf->get_item_handler( Upload_Handler::get_item_handler_key_name() );
		$upload_handler->handle( $as3cf_item, array( 'offloaded_files' => $offloaded_files ) );
	}

	/**
	 * Handle update_post_metadata for some media library related keys
	 *
	 * @handles update_post_metadata
	 *
	 * @param bool   $check
	 * @param int    $object_id
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 * @param mixed  $prev_value
	 */
	public function update_post_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		if ( '_wp_attachment_backup_sizes' === $meta_key ) {
			if ( $this->as3cf->is_plugin_setup( true ) ) {
				$this->update_attachment_backup_sizes( $object_id, $meta_value );
			}
		}

		return $check;
	}

	/**
	 * Handle updated attachment_backup_sizes.
	 *
	 * @param int   $post_id
	 * @param array $sizes
	 */
	protected function update_attachment_backup_sizes( $post_id, $sizes ) {
		// This item should already be known in this request, if not bail out
		if ( empty( $this->items_in_progress[ $post_id ] ) ) {
			return;
		}

		// We should also have recorded some replaced keys in this request, if not bail
		if ( empty( $this->replaced_object_keys[ $post_id ] ) ) {
			return;
		}

		/** @var Media_Library_Item $as3cf_item */
		$as3cf_item       = $this->items_in_progress[ $post_id ];
		$existing_objects = $as3cf_item->objects();

		foreach ( array_keys( $sizes ) as $key ) {
			if ( ! isset( $existing_objects[ $key ] ) ) {
				$parts = explode( '-', $key );
				$size  = join( '-', array_slice( $parts, 0, -1 ) );
				if ( 'full' === $size ) {
					$size = Item::primary_object_key();
				}

				if ( isset( $this->replaced_object_keys[ $post_id ][ $size ] ) ) {
					$existing_objects[ $key ] = $this->replaced_object_keys[ $post_id ][ $size ];
				}
			}
		}

		$as3cf_item->set_objects( $existing_objects );
		$as3cf_item->save();
	}

	/**
	 * Filters the result when generating a unique file name.
	 *
	 * @param string $filename Unique file name.
	 * @param string $ext      File extension, eg. ".png".
	 * @param string $dir      Directory path.
	 *
	 * @return string
	 * @since 4.5.0
	 */
	public function wp_unique_filename( $filename, $ext, $dir ) {
		// Get Post ID if uploaded in post screen.
		$post_id = filter_input( INPUT_POST, 'post_id', FILTER_VALIDATE_INT );

		return $this->filter_unique_filename( $filename, $ext, $dir, $post_id );
	}

	/**
	 * Create unique names for file to be uploaded to AWS.
	 * This only applies when the remove local file option is enabled.
	 *
	 * @param string $filename Unique file name.
	 * @param string $ext      File extension, eg. ".png".
	 * @param string $dir      Directory path.
	 * @param int    $post_id  Attachment's parent Post ID.
	 *
	 * @return string
	 */
	public function filter_unique_filename( $filename, $ext, $dir, $post_id = null ) {
		if ( ! $this->as3cf->is_plugin_setup( true ) ) {
			return $filename;
		}

		// sanitize the file name before we begin processing
		$filename = sanitize_file_name( $filename );
		$ext      = strtolower( $ext );
		$name     = wp_basename( $filename, $ext );

		// Edge case: if file is named '.ext', treat as an empty name.
		if ( $name === $ext ) {
			$name = '';
		}

		// Rebuild filename with lowercase extension as provider will have converted extension on upload.
		$filename = $name . $ext;
		$time     = current_time( 'mysql' );

		// Get time if uploaded in post screen.
		if ( ! empty( $post_id ) ) {
			$time = $this->get_post_time( $post_id );
		}

		if ( ! $this->as3cf->does_file_exist( $filename, $time ) ) {
			// File doesn't exist locally or on provider, return it.
			return $filename;
		}

		return $this->as3cf->generate_unique_filename( $name, $ext, $time );
	}

	/**
	 * Allow processes to update the file on provider via update_attached_file()
	 *
	 * @param string $file
	 * @param int    $attachment_id
	 *
	 * @return string
	 */
	public function update_attached_file( $file, $attachment_id ) {
		if ( ! $this->as3cf->is_plugin_setup( true ) ) {
			return $file;
		}

		$as3cf_item = Media_Library_Item::get_by_source_id( $attachment_id );

		if ( ! $as3cf_item ) {
			return $file;
		}

		/**
		 * Allow processes to update the file on provider via update_attached_file()
		 *
		 * @param string             $file          File name/path
		 * @param int                $attachment_id Attachment id
		 * @param Media_Library_Item $as3cf_item    The item object
		 */
		return apply_filters( 'as3cf_update_attached_file', $file, $attachment_id, $as3cf_item );
	}

	/**
	 * Removes an attachment and intermediate image size files from provider
	 *
	 * @param int $post_id
	 */
	public function delete_attachment( $post_id ) {
		if ( ! $this->as3cf->is_plugin_setup( true ) ) {
			return;
		}

		$as3cf_item = Media_Library_Item::get_by_source_id( $post_id );

		if ( ! $as3cf_item ) {
			return;
		}

		if ( ! $as3cf_item->served_by_provider( true ) ) {
			return;
		}

		// Remove the objects from the provider
		$remove_provider_handler = $this->as3cf->get_item_handler( Remove_Provider_Handler::get_item_handler_key_name() );
		$remove_provider_handler->handle( $as3cf_item, array( 'verify_exists_on_local' => false ) );
		$as3cf_item->delete();
	}

	/**
	 * Update an existing item's expected objects from attachment's new metadata.
	 *
	 * @param Media_Library_Item $as3cf_item
	 * @param array              $metadata
	 */
	protected function update_item_from_new_metadata( $as3cf_item, $metadata ) {
		if ( empty( $metadata ) || ! is_array( $metadata ) ) {
			return;
		}

		$files             = AS3CF_Utils::get_attachment_file_paths( $as3cf_item->source_id(), false, $metadata );
		$existing_basename = wp_basename( $as3cf_item->path() );
		$existing_objects  = $as3cf_item->objects();

		if ( ! isset( $this->replaced_object_keys[ $as3cf_item->source_id() ] ) ) {
			$this->replaced_object_keys[ $as3cf_item->source_id() ] = array();
		}

		foreach ( $files as $object_key => $file ) {
			$new_filename = wp_basename( $file );

			if ( ! empty( $existing_objects[ $object_key ]['source_file'] ) && $existing_objects[ $object_key ]['source_file'] !== $new_filename ) {
				$this->replaced_object_keys[ $as3cf_item->source_id() ][ $object_key ] = $existing_objects[ $object_key ];
			}

			if ( Item::primary_object_key() === $object_key && $existing_basename !== $new_filename ) {
				$as3cf_item->set_path( str_replace( $existing_basename, $new_filename, $as3cf_item->path() ) );
				$as3cf_item->set_source_path( str_replace( $existing_basename, $new_filename, $as3cf_item->source_path() ) );
			}

			$existing_objects[ $object_key ] = array(
				'source_file' => $new_filename,
				'is_private'  => isset( $existing_objects[ $object_key ]['is_private'] ) ? $existing_objects[ $object_key ]['is_private'] : false,
			);
		}

		$extra_info            = $as3cf_item->extra_info();
		$extra_info['objects'] = $existing_objects;
		$as3cf_item->set_extra_info( $extra_info );
	}

	/**
	 * Load media assets.
	 */
	public function load_media_assets() {
		$this->as3cf->enqueue_style( 'as3cf-media-styles', 'assets/css/media', array( 'as3cf-modal' ) );
		$this->as3cf->enqueue_script( 'as3cf-media-script', 'assets/js/media', array(
			'jquery',
			'media-views',
			'media-grid',
			'wp-util',
		) );

		wp_localize_script( 'as3cf-media-script', 'as3cf_media', array(
			'strings' => $this->get_media_action_strings(),
			'nonces'  => array(
				'get_attachment_provider_details' => wp_create_nonce( 'get-attachment-s3-details' ),
			),
		) );
	}

	/**
	 * Load the attachment assets only when editing an attachment
	 *
	 * @param string $hook_suffix
	 */
	public function load_attachment_assets( $hook_suffix ) {
		global $post;
		if ( 'post.php' !== $hook_suffix || 'attachment' !== $post->post_type ) {
			return;
		}

		$this->as3cf->enqueue_style( 'as3cf-pro-attachment-styles', 'assets/css/attachment', array( 'as3cf-modal' ) );

		do_action( 'as3cf_load_attachment_assets' );
	}

	/**
	 * Add the S3 meta box to the attachment screen
	 */
	public function attachment_provider_meta_box() {
		add_meta_box(
			's3-actions',
			__( 'Offload', 'amazon-s3-and-cloudfront' ),
			array( $this, 'attachment_provider_actions_meta_box' ),
			'attachment',
			'side',
			'core'
		);
	}

	/**
	 * Handle retrieving the provider details for attachment modals.
	 */
	public function ajax_get_attachment_provider_details() {
		if ( ! isset( $_POST['id'] ) ) {
			return;
		}

		check_ajax_referer( 'get-attachment-s3-details', '_nonce' );

		$id                 = intval( $_POST['id'] );
		$as3cf_item         = Media_Library_Item::get_by_source_id( $id );
		$served_by_provider = false;

		if ( ! empty( $as3cf_item ) ) {
			$served_by_provider = $as3cf_item->served_by_provider( true );
		}

		$this->render_context = 'grid';

		// get the actions available for the attachment
		$data = array(
			'links'           => $this->add_media_row_actions( array(), $id ),
			'provider_object' => $this->get_formatted_provider_info( $id ),
			'acl_toggle'      => $this->verify_media_actions() && $served_by_provider,
		);

		wp_send_json_success( $data );
	}

	/**
	 * Conditionally adds copy, remove and download S3 action links for an
	 * attachment on the Media library list view
	 *
	 * @param array       $actions
	 * @param WP_Post|int $post
	 *
	 * @return array
	 */
	public function add_media_row_actions( array $actions, $post ) {
		return $actions;
	}

	/**
	 * Get a list of available media actions which can be performed according to plugin and user capability requirements.
	 *
	 * @param string|null $scope
	 *
	 * @return array
	 */
	public function get_available_media_actions( $scope = '' ) {
		return array();
	}

	/**
	 * Render the S3 attachment meta box
	 */
	public function attachment_provider_actions_meta_box() {
		global $post;
		$file = get_attached_file( $post->ID, true );

		$args = array(
			'provider_object'   => $this->get_formatted_provider_info( $post->ID ),
			'post'              => $post,
			'local_file_exists' => file_exists( $file ),
			'available_actions' => $this->get_available_media_actions( 'singular' ),
			'sendback'          => 'post.php?post=' . $post->ID . '&action=edit',
		);

		$this->as3cf->render_view( 'attachment-metabox', $args );
	}

	/**
	 * Get attachment url
	 *
	 * @param string $url
	 * @param int    $post_id
	 *
	 * @return bool|mixed|WP_Error
	 */
	public function wp_get_attachment_url( $url, $post_id ) {
		if ( $this->as3cf->plugin_compat->is_customizer_crop_action() ) {
			return $url;
		}

		$as3cf_item = Media_Library_Item::get_by_source_id( $post_id );
		if ( empty( $as3cf_item ) || ! $as3cf_item->served_by_provider() ) {
			return $url;
		}

		$size    = $as3cf_item->get_object_key_from_filename( $url );
		$new_url = $as3cf_item->get_provider_url( $size );

		if ( is_wp_error( $new_url ) || false === $new_url ) {
			return $url;
		}

		// Old naming convention, will be deprecated soon
		$new_url = apply_filters( 'wps3_get_attachment_url', $new_url, $post_id, $this );

		/**
		 * Filter the rewritten provider URL for a Media Library Item (attachment)
		 *
		 * @param string $url     The URL
		 * @param int    $post_id Attachment post id
		 */
		return apply_filters( 'as3cf_wp_get_attachment_url', $new_url, $post_id );
	}

	/**
	 * Return a formatted provider info array with display friendly defaults
	 *
	 * @param int $id
	 *
	 * @return bool|array
	 */
	public function get_formatted_provider_info( int $id ) {
		$as3cf_item = Media_Library_Item::get_by_source_id( $id );

		if ( ! $as3cf_item ) {
			return false;
		}

		$provider_object  = $as3cf_item->key_values();
		$storage_provider = $this->as3cf->get_storage_provider_instance( $provider_object['provider'] );

		// Backwards compatibility.
		$provider_object['key'] = $provider_object['path'];
		$provider_object['url'] = $as3cf_item->get_provider_url();

		$acl      = $as3cf_item->is_private() ? $storage_provider->get_private_acl() : $storage_provider->get_default_acl();
		$acl_info = array(
			'acl'   => $acl,
			'name'  => $this->as3cf->get_acl_display_name( $acl ),
			'title' => $this->get_media_action_strings( 'change_to_private' ),
		);

		if ( $as3cf_item->is_private() ) {
			$acl_info['title'] = $this->get_media_action_strings( 'change_to_public' );
		}

		$provider_object['acl']           = $acl_info;
		$provider_object['region']        = $storage_provider->get_region_name( $provider_object['region'] );
		$provider_object['provider_name'] = $this->as3cf->get_provider_service_name( $provider_object['provider'] );

		return $provider_object;
	}

	/**
	 * Filters the list of attachment image attributes.
	 *
	 * @param array        $attr       Attributes for the image markup.
	 * @param WP_Post      $attachment Image attachment post.
	 * @param string|array $size       Requested size. Image size or array of width and height values (in that order).
	 *
	 * @return array
	 */
	public function wp_get_attachment_image_attributes( $attr, $attachment, $size ) {
		$as3cf_item = Media_Library_Item::get_by_source_id( $attachment->ID );
		if ( ! $as3cf_item || ! $as3cf_item->served_by_provider() ) {
			return $attr;
		}

		$size = $this->maybe_convert_size_to_string( $attachment->ID, $size );

		// image_downsize incorrectly substitutes size filename into full URL for src attribute instead of clobbering.
		// So we need to fix up the src attribute if a size is being used.
		if ( ! empty( $size ) && ! empty( $attr['src'] ) ) {
			$attr['src'] = $as3cf_item->get_provider_url( $size );
		}

		/**
		 * Filtered list of attachment image attributes.
		 *
		 * @param array              $attr       Attributes for the image markup.
		 * @param WP_Post            $attachment Image attachment post.
		 * @param string             $size       Requested size.
		 * @param Media_Library_Item $as3cf_item
		 */
		return apply_filters( 'as3cf_wp_get_attachment_image_attributes', $attr, $attachment, $size, $as3cf_item );
	}

	/**
	 * Maybe encode attachment URLs when retrieving the image tag
	 *
	 * @param string $html
	 * @param int    $id
	 * @param string $alt
	 * @param string $title
	 * @param string $align
	 * @param string $size
	 *
	 * @return string
	 */
	public function maybe_encode_get_image_tag( $html, $id, $alt, $title, $align, $size ) {
		$as3cf_item = Media_Library_Item::get_by_source_id( $id );
		if ( ! $as3cf_item || ! $as3cf_item->served_by_provider() ) {
			return $html;
		}

		if ( ! is_string( $html ) ) {
			return $html;
		}

		preg_match( '@\ssrc=[\'\"]([^\'\"]*)[\'\"]@', $html, $matches );

		if ( ! isset( $matches[1] ) ) {
			// Can't establish img src
			return $html;
		}

		$img_src     = $matches[1];
		$new_img_src = $this->maybe_sign_intermediate_size( $img_src, $id, $size, $as3cf_item );
		$new_img_src = AS3CF_Utils::encode_filename_in_path( $new_img_src );

		return str_replace( $img_src, $new_img_src, $html );
	}

	/**
	 * Maybe encode URLs for images that represent an attachment
	 *
	 * @param array|bool   $image
	 * @param int          $attachment_id
	 * @param string|array $size
	 * @param bool         $icon
	 *
	 * @return array
	 */
	public function maybe_encode_wp_get_attachment_image_src( $image, $attachment_id, $size, $icon ) {
		$as3cf_item = Media_Library_Item::get_by_source_id( $attachment_id );
		if ( ! $as3cf_item || ! $as3cf_item->served_by_provider() ) {
			return $image;
		}

		if ( isset( $image[0] ) ) {
			$url = $this->maybe_sign_intermediate_size( $image[0], $attachment_id, $size, $as3cf_item );
			$url = AS3CF_Utils::encode_filename_in_path( $url );

			$image[0] = $url;
		}

		return $image;
	}

	/**
	 * Maybe encode URLs when outputting attachments in the media grid
	 *
	 * @param array      $response
	 * @param int|object $attachment
	 * @param array      $meta
	 *
	 * @return array
	 */
	public function maybe_encode_wp_prepare_attachment_for_js( $response, $attachment, $meta ) {
		$as3cf_item = Media_Library_Item::get_by_source_id( $attachment->ID );
		if ( empty( $as3cf_item ) || ! $as3cf_item->served_by_provider() ) {
			return $response;
		}

		if ( isset( $response['url'] ) ) {
			$response['url'] = AS3CF_Utils::encode_filename_in_path( $response['url'] );
		}

		if ( isset( $response['sizes'] ) && is_array( $response['sizes'] ) ) {
			foreach ( $response['sizes'] as $size => $value ) {
				$url = $this->maybe_sign_intermediate_size( $value['url'], $attachment->ID, $size, $as3cf_item, true );
				$url = AS3CF_Utils::encode_filename_in_path( $url );

				$response['sizes'][ $size ]['url'] = $url;
			}
		}

		return $response;
	}

	/**
	 * Maybe encode URLs when retrieving intermediate sizes.
	 *
	 * @param array        $data
	 * @param int          $post_id
	 * @param string|array $size
	 *
	 * @return array
	 */
	public function maybe_encode_image_get_intermediate_size( $data, $post_id, $size ) {
		$as3cf_item = Media_Library_Item::get_by_source_id( $post_id );
		if ( ! $as3cf_item || ! $as3cf_item->served_by_provider() ) {
			return $data;
		}

		if ( isset( $data['url'] ) ) {
			$url = $this->maybe_sign_intermediate_size( $data['url'], $post_id, $size, $as3cf_item );
			$url = AS3CF_Utils::encode_filename_in_path( $url );

			$data['url'] = $url;
		}

		return $data;
	}

	/**
	 * Sign intermediate size.
	 *
	 * @param string                  $url
	 * @param int                     $attachment_id
	 * @param string|array            $size
	 * @param bool|Media_Library_Item $as3cf_item
	 * @param bool                    $force_rewrite If size not signed, make sure correct URL is being used anyway.
	 *
	 * @return string|WP_Error
	 */
	protected function maybe_sign_intermediate_size( $url, $attachment_id, $size, $as3cf_item = false, $force_rewrite = false ) {
		if ( ! $as3cf_item ) {
			$as3cf_item = Media_Library_Item::get_by_source_id( $attachment_id );
		}

		$size = $this->maybe_convert_size_to_string( $attachment_id, $size );

		if ( $force_rewrite || $as3cf_item->is_private( $size ) ) {
			// Private file, add AWS signature if required
			return $as3cf_item->get_provider_url( $size );
		}

		return $url;
	}

	/**
	 * Return the provider URL when the local file is missing
	 * unless we know who the calling process is, and we are happy
	 * to copy the file back to the server to be used.
	 *
	 * @handles get_attached_file
	 * @handles wp_get_original_image_path
	 *
	 * @param string $file
	 * @param int    $attachment_id
	 *
	 * @return string
	 */
	public function get_attached_file( $file, $attachment_id ) {
		// During the deletion of an attachment, stream wrapper URLs should not be returned.
		if ( $this->deleting_attachment ) {
			return $file;
		}

		$as3cf_item = Media_Library_Item::get_by_source_id( $attachment_id );
		if ( ! empty( $as3cf_item ) && ! $as3cf_item->served_by_provider() ) {
			$as3cf_item = false;
		}

		if ( file_exists( $file ) || ! $as3cf_item ) {
			if ( $as3cf_item ) {
				/**
				 * This filter gives filter implementors a chance to copy back siblings for
				 * a local file even if the main already exists locally.
				 *
				 * @param string             $url           Item URL
				 * @param string             $file          Local file path
				 * @param int                $attachment_id Attachment post id
				 * @param Media_Library_Item $as3cf_item    The Item object
				 */
				return apply_filters( 'as3cf_get_attached_file_noop', $file, $file, $attachment_id, $as3cf_item );
			} else {
				return $file;
			}
		}

		$url = $as3cf_item->get_provider_url();
		if ( false === $url || is_wp_error( $url ) ) {
			return $file;
		}

		/**
		 * This filter gives filter implementors a chance to copy back missing item files
		 * from the provider before WordPress returns the file name/path for it. Defaults to
		 * returning the remote URL.
		 *
		 * @param string             $url           Item URL
		 * @param string             $file          Local file path
		 * @param int                $attachment_id Attachment post id
		 * @param Media_Library_Item $as3cf_item    The Item object
		 */
		return apply_filters( 'as3cf_get_attached_file', $url, $file, $attachment_id, $as3cf_item );
	}

	/**
	 * Filters the audio & video shortcodes output to remove "&_=NN" params from source.src as it breaks signed URLs.
	 *
	 * @param string $html    Shortcode HTML output.
	 * @param array  $atts    Array of shortcode attributes.
	 * @param string $media   Media file.
	 * @param int    $post_id Post ID.
	 * @param string $library Media library used for the shortcode.
	 *
	 * @return string
	 *
	 * Note: Depends on 30377.4.diff from https://core.trac.wordpress.org/ticket/30377
	 */
	public function wp_media_shortcode( $html, $atts, $media, $post_id, $library ) {
		return preg_replace( '/&#038;_=[0-9]+/', '', $html );
	}

	/**
	 * Check we can do the media actions
	 *
	 * @return bool
	 */
	public function verify_media_actions() {
		return false;
	}

	/**
	 * Get all strings or a specific string used for the media actions
	 *
	 * @param null|string $string
	 *
	 * @return array|string
	 */
	public function get_media_action_strings( $string = null ) {
		$not_verified_value = __( 'No', 'amazon-s3-and-cloudfront' );
		$not_verified_value .= '&nbsp;';
		$not_verified_value .= $this->as3cf->more_info_link( '/wp-offload-media/doc/add-metadata-tool/', 'os3+attachment+metabox', 'analyze-and-repair', 'More Info', '(', ')' );

		/**
		 * Returns all strings used to render meta boxes on the WordPress Media Library edit page
		 *
		 * @param array $strings Associative array of strings
		 */
		$strings = apply_filters( 'as3cf_media_action_strings', array(
			'provider'      => _x( 'Storage Provider', 'Storage provider key name', 'amazon-s3-and-cloudfront' ),
			'provider_name' => _x( 'Storage Provider', 'Storage provider name', 'amazon-s3-and-cloudfront' ),
			'bucket'        => _x( 'Bucket', 'Bucket name', 'amazon-s3-and-cloudfront' ),
			'key'           => _x( 'Path', 'Path to file in bucket', 'amazon-s3-and-cloudfront' ),
			'region'        => _x( 'Region', 'Location of bucket', 'amazon-s3-and-cloudfront' ),
			'acl'           => _x( 'Access', 'Access control list of the file in bucket', 'amazon-s3-and-cloudfront' ),
			'url'           => __( 'URL', 'amazon-s3-and-cloudfront' ),
			'is_verified'   => _x( 'Verified', 'Whether or not metadata has been verified', 'amazon-s3-and-cloudfront' ),
			'not_verified'  => $not_verified_value,
		) );

		if ( ! is_null( $string ) ) {
			return isset( $strings[ $string ] ) ? $strings[ $string ] : '';
		}

		return $strings;
	}

	/**
	 * Remove 'filesize' from attachment's metadata if appropriate, also our total filesize record.
	 *
	 * @param int   $post_id         Attachment's post_id.
	 * @param array $data            Attachment's metadata.
	 * @param bool  $update_metadata Update the metadata record now? Defaults to true.
	 *
	 * @return array Attachment's cleaned up metadata.
	 */
	public function maybe_cleanup_filesize_metadata( $post_id, $data, $update_metadata = true ) {
		if ( ! is_int( $post_id ) || empty( $post_id ) || empty( $data ) || ! is_array( $data ) ) {
			return $data;
		}

		/*
		 * Audio and video have a filesize added to metadata by default, but images and anything else don't.
		 * Note: Could have used `wp_generate_attachment_metadata` here to test whether default metadata has 'filesize',
		 * but it not only has side effects it also does a lot of work considering it's not a huge deal for this entry to hang around.
		 */
		if (
			empty( $data['mime_type'] ) ||
			0 === strpos( $data['mime_type'], 'image/' ) ||
			! ( 0 === strpos( $data['mime_type'], 'audio/' ) || 0 === strpos( $data['mime_type'], 'video/' ) )
		) {
			unset( $data['filesize'] );
		}

		if ( $update_metadata ) {
			if ( empty( $data ) ) {
				delete_post_meta( $post_id, '_wp_attachment_metadata' );
			} else {
				update_post_meta( $post_id, '_wp_attachment_metadata', $data );
			}
		}

		delete_post_meta( $post_id, 'as3cf_filesize_total' );

		return $data;
	}

	/**
	 * Get ACL value string.
	 *
	 * @param array $acl
	 * @param int   $post_id
	 *
	 * @return string
	 */
	public function get_acl_value_string( $acl, $post_id ) {
		return $acl['name'];
	}

	/**
	 * Determines if the image metadata is for the image source file.
	 *
	 * @handles wp_image_file_matches_image_meta
	 *
	 * @param bool   $match
	 * @param string $image_location
	 * @param array  $image_meta
	 * @param int    $source_id
	 *
	 * @return bool
	 */
	public function image_file_matches_image_meta( $match, $image_location, $image_meta, $source_id ) {
		// If already matched or the URL is local, there's nothing for us to do.
		if ( $match || $this->as3cf->filter_local->url_needs_replacing( $image_location ) ) {
			return $match;
		}

		$item = array(
			'id'          => $source_id,
			'source_type' => Media_Library_Item::source_type(),
		);

		return $this->as3cf->filter_provider->item_matches_src( $item, $image_location );
	}

	/**
	 * Get the local URL for a Media Library Item
	 *
	 * @handles as3cf_get_local_url_for_item_source
	 *
	 * @param string $url         Url
	 * @param array  $item_source The item source descriptor array
	 * @param string $size        Name of requested size
	 *
	 * @return string|false
	 */
	public function filter_get_local_url_for_item_source( $url, $item_source, $size ) {
		if ( Media_Library_Item::source_type() !== $item_source['source_type'] ) {
			return $url;
		}

		$as3cf_item = Media_Library_Item::get_by_source_id( $item_source['id'] );
		if ( ! empty( $as3cf_item ) ) {
			return $as3cf_item->get_local_url( $size );
		}

		return $url;
	}

	/**
	 * Get the remote URL for a Media Library Item
	 *
	 * @handles as3cf_get_provider_url_for_item_source
	 *
	 * @param string $url         Url
	 * @param array  $item_source The item source descriptor array
	 * @param string $size        Name of requested size
	 *
	 * @return string|false
	 */
	public function filter_get_provider_url_for_item_source( $url, $item_source, $size ) {
		if ( Media_Library_Item::source_type() !== $item_source['source_type'] ) {
			return $url;
		}

		$as3cf_item = Media_Library_Item::get_by_source_id( $item_source['id'] );
		if ( empty( $as3cf_item ) || ! $as3cf_item->served_by_provider() ) {
			return $url;
		}

		$url = $as3cf_item->get_provider_url( $size );

		if ( is_wp_error( $url ) ) {
			return false;
		}

		return $url;
	}

	/**
	 * Get the size from a URL for media library item types
	 *
	 * @handles as3cf_get_size_string_from_url_for_item_source
	 *
	 * @param string $size
	 * @param string $url
	 * @param array  $item_source
	 *
	 * @return string
	 */
	public function get_size_string_from_url_for_item_source( $size, $url, $item_source ) {
		if ( Media_Library_Item::source_type() !== $item_source['source_type'] ) {
			return $size;
		}

		$meta = get_post_meta( $item_source['id'], '_wp_attachment_metadata', true );

		if ( empty( $meta['sizes'] ) ) {
			// No alternative sizes available, return
			return $size;
		}

		$basename = AS3CF_Utils::encode_filename_in_path( wp_basename( $this->as3cf->maybe_remove_query_string( $url ) ) );

		foreach ( $meta['sizes'] as $size_name => $file ) {
			if ( $basename === AS3CF_Utils::encode_filename_in_path( $file['file'] ) ) {
				return $size_name;
			}
		}

		return $size;
	}

	/**
	 * Get attachment id from remote URL.
	 *
	 * @param string $url
	 *
	 * @return bool|int
	 */
	public function get_attachment_id_from_provider_url( $url ) {
		$item_source = $this->as3cf->filter_provider->get_item_source_from_url( $url );

		if ( ! Item::is_empty_item_source( $item_source ) && Media_Library_Item::source_type() === $item_source['source_type'] ) {
			return $item_source['id'];
		}

		return false;
	}

	/**
	 * Get attachment id from local URL.
	 *
	 * @param string $url
	 *
	 * @return bool|int
	 */
	public function get_attachment_id_from_local_url( $url ) {
		$item_source = $this->as3cf->filter_local->get_item_source_from_url( $url );

		if ( ! Item::is_empty_item_source( $item_source ) && Media_Library_Item::source_type() === $item_source['source_type'] ) {
			return $item_source['id'];
		}

		return false;
	}

	/**
	 * Get post time
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	private function get_post_time( $post_id ) {
		$time = current_time( 'mysql' );

		if ( ! $post = get_post( $post_id ) ) {
			return $time;
		}

		if ( substr( $post->post_date, 0, 4 ) > 0 ) {
			$time = $post->post_date;
		}

		return $time;
	}

	/**
	 * Maybe convert size to string
	 *
	 * @param int   $attachment_id
	 * @param mixed $size
	 *
	 * @return null|string
	 */
	private function maybe_convert_size_to_string( $attachment_id, $size ) {
		if ( is_array( $size ) ) {
			return $this->convert_dimensions_to_size_name( $attachment_id, $size );
		}

		return $size;
	}

	/**
	 * Convert dimensions to size.
	 *
	 * @param int   $attachment_id
	 * @param array $dimensions
	 *
	 * @return null|string
	 */
	private function convert_dimensions_to_size_name( int $attachment_id, array $dimensions ) {
		$w                     = ( isset( $dimensions[0] ) && (int) $dimensions[0] > 0 ) ? (int) $dimensions[0] : 1;
		$h                     = ( isset( $dimensions[1] ) && (int) $dimensions[1] > 0 ) ? (int) $dimensions[1] : 1;
		$original_aspect_ratio = $w / $h;
		$meta                  = wp_get_attachment_metadata( $attachment_id );

		if ( ! isset( $meta['sizes'] ) || empty( $meta['sizes'] ) ) {
			return null;
		}

		$sizes = $meta['sizes'];
		uasort( $sizes, function ( $a, $b ) {
			// Order by image area.
			return ( (int) $a['width'] * (int) $a['height'] ) - ( (int) $b['width'] * (int) $b['height'] );
		} );

		$nearest_matches = array();

		foreach ( $sizes as $size => $value ) {
			if ( $w > (int) $value['width'] || $h > (int) $value['height'] ) {
				continue;
			}

			$aspect_ratio = 0;
			if ( (int) $value['height'] > 0 ) {
				$aspect_ratio = (int) $value['width'] / (int) $value['height'];
			}

			if ( $aspect_ratio === $original_aspect_ratio ) {
				return $size;
			}

			$nearest_matches[] = $size;
		}

		// Return nearest match.
		if ( ! empty( $nearest_matches ) ) {
			return $nearest_matches[0];
		}

		return null;
	}

	/**
	 * Has the given attachment been uploaded by this instance?
	 *
	 * @param int $source_id
	 *
	 * @return bool
	 */
	public function item_just_uploaded( $source_id ) {
		if ( is_int( $source_id ) && isset( $this->items_in_progress[ $source_id ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Call legacy attachment specific version of the as3cf_get_item_secure_url filter.
	 *
	 * @param string $url         The URL
	 * @param Item   $as3cf_item  The Item object
	 * @param array  $item_source The item source descriptor array
	 * @param int    $timestamp   Expiry timestamp
	 * @param array  $headers     Optional extra http headers
	 *
	 * @handles as3cf_get_item_secure_url
	 *
	 * @return string|mixed
	 */
	public function get_item_secure_url( $url, $as3cf_item, $item_source, $timestamp, $headers ) {
		if ( Media_Library_Item::source_type() !== $item_source['source_type'] ) {
			return $url;
		}

		/**
		 * Filters the secure url for an attachment
		 *
		 * @param string $url        The URL
		 * @param Item   $as3cf_item The Item object
		 * @param int    $id         The attachment id
		 * @param int    $timestamp  Expiry timestamp
		 * @param array  $headers    Optional extra http headers
		 *
		 * @deprecated 2.6.0 Please use filter "as3cf_get_item_secure_url" instead.
		 */
		return apply_filters( 'as3cf_get_attachment_secure_url', $url, $as3cf_item, $item_source['id'], $timestamp, $headers );
	}

	/**
	 * Call legacy attachment specific version of the as3cf_get_item_url filter.
	 *
	 * @param string $url         The URL
	 * @param Item   $as3cf_item  The Item object
	 * @param array  $item_source The item source descriptor array
	 * @param int    $timestamp   Expiry timestamp
	 * @param array  $headers     Optional extra http headers
	 *
	 * @handles as3cf_get_item_url
	 *
	 * @return string|mixed
	 */
	public function get_item_url( $url, $as3cf_item, $item_source, $timestamp, $headers ) {
		if ( Media_Library_Item::source_type() !== $item_source['source_type'] ) {
			return $url;
		}

		/**
		 * Filters the url for an attachment
		 *
		 * @param string $url        The URL
		 * @param Item   $as3cf_item The Item object
		 * @param int    $id         The attachment id
		 * @param int    $timestamp  Expiry timestamp
		 * @param array  $headers    Optional extra http headers
		 *
		 * @deprecated 2.6.0 Please use filter "as3cf_get_item_url" instead.
		 */
		return apply_filters( 'as3cf_get_attachment_url', $url, $as3cf_item, $item_source['id'], $timestamp, $headers );
	}

	/**
	 * Call legacy attachment specific version of the as3cf_remove_source_files_from_provider filter.
	 *
	 * @param array $paths       Array of local paths to be removed from provider
	 * @param Item  $as3cf_item  The Item object
	 * @param array $item_source The item source descriptor array
	 *
	 * @handles as3cf_remove_source_files_from_provider
	 *
	 * @return array|mixed
	 */
	public function filter_remove_source_files_from_provider( $paths, Item $as3cf_item, $item_source ) {
		if ( Media_Library_Item::source_type() !== $item_source['source_type'] ) {
			return $paths;
		}

		/**
		 * Filters which provider files to remove
		 *
		 * @param array $paths           Array of local paths to be removed from provider
		 * @param int   $id              Item attachment id
		 * @param Item  $as3cf_item      The Item
		 * @param bool  $include_backups Also include backup files?
		 *
		 * @deprecated 2.6.0 Please use filter "as3cf_remove_source_files_from_provider" instead.
		 */
		return apply_filters( 'as3cf_remove_attachment_paths', $paths, $item_source['id'], $as3cf_item, true );
	}

	/**
	 * Calls legacy attachment specific version of as3cf_remove_local_files filter.
	 *
	 * @param array $files_to_remove Array of paths to be removed
	 * @param Item  $as3cf_item      The Item object
	 * @param array $item_source     The item source descriptor array
	 *
	 * @handles as3cf_remove_local_files
	 *
	 * @return array|mixed
	 */
	public function filter_remove_local_files( $files_to_remove, $as3cf_item, $item_source ) {
		if ( Media_Library_Item::source_type() !== $item_source['source_type'] ) {
			return $files_to_remove;
		}

		/**
		 * Filters which local files should be removed.
		 *
		 * @param array  $paths       Paths that will be removed
		 * @param int    $id          Attachment id
		 * @param string $source_path Path to primary file
		 *
		 * @deprecated 2.6.0 Please use filter "as3cf_remove_local_files" instead.
		 */
		return apply_filters( 'as3cf_upload_attachment_local_files_to_remove', $files_to_remove, $item_source['id'], $as3cf_item->full_source_path( Item::primary_object_key() ) );
	}

	/**
	 * Handle post upload duties if uploaded item is a media-library item.
	 *
	 * @handles as3cf_post_upload_item
	 *
	 * @param Media_Library_Item $as3cf_item
	 */
	public function post_upload_item( $as3cf_item ) {
		if ( Media_Library_Item::source_type() !== $as3cf_item->source_type() ) {
			return;
		}

		// Make sure duplicates are marked as offloaded too.
		$as3cf_item->offload_duplicate_items();

		/**
		 * Fires after an attachment has been uploaded to the provider.
		 *
		 * @param int  $id         Attachment id
		 * @param Item $as3cf_item The item that was just uploaded
		 *
		 * @deprecated 2.6.0 Please use action "as3cf_post_upload_item" instead.
		 */
		do_action( 'as3cf_post_upload_attachment', $as3cf_item->source_id(), $as3cf_item );
	}

	/**
	 * Call legacy media library specific filter for cancelling an upload.
	 *
	 * @param bool  $cancel     Should the action on the item be cancelled?
	 * @param Item  $as3cf_item The item that the action is being handled for.
	 * @param array $options    Handler dependent options that may have been set for the action.
	 *
	 * @handles as3cf_pre_handle_item_upload
	 *
	 * @return bool
	 */
	public function pre_handle_item_upload( $cancel, $as3cf_item, array $options ) {
		if ( Media_Library_Item::source_type() !== $as3cf_item->source_type() ) {
			return $cancel;
		}

		// Get unfiltered attachment metadata to pass into legacy filter.
		$metadata = wp_get_attachment_metadata( $as3cf_item->source_id(), true );
		if ( is_wp_error( $metadata ) ) {
			return $metadata;
		}

		/**
		 * Allow provider upload to be cancelled for any reason.
		 *
		 * @param bool  $cancel   Should the upload for the attachment be cancelled?
		 * @param int   $id       Attachment id
		 * @param array $metadata Attachment metadata
		 *
		 * @deprecated 2.6.0 Please use filter "as3cf_pre_upload_item" instead.
		 */
		return apply_filters( 'as3cf_pre_upload_attachment', $cancel, $as3cf_item->source_id(), $metadata );
	}

	/**
	 * Call legacy filter for determining private status on an item's individual object_key.
	 *
	 * @param bool   $is_private
	 * @param string $object_key
	 * @param Item   $as3cf_item
	 *
	 * @handles as3cf_upload_object_key_as_private
	 *
	 * @return bool
	 */
	public function filter_upload_object_key_as_private( $is_private, $object_key, $as3cf_item ) {
		if ( Media_Library_Item::source_type() !== $as3cf_item->source_type() ) {
			return $is_private;
		}

		$metadata    = wp_get_attachment_metadata( $as3cf_item->source_id(), true );
		$default_acl = $this->as3cf->get_storage_provider()->get_default_acl();
		$private_acl = $this->as3cf->get_storage_provider()->get_private_acl();
		$acl         = true === $is_private ? $private_acl : $default_acl;

		if ( Item::primary_object_key() === $object_key ) {
			$file_name = wp_basename( $as3cf_item->source_path() );
			$file_type = wp_check_filetype_and_ext( $as3cf_item->source_path(), $file_name );

			// Old naming convention, will be removed soon.
			$acl = apply_filters( 'wps3_upload_acl', $acl, $file_type['type'], $metadata, $as3cf_item->source_id(), $this->as3cf );

			/**
			 * Determine canned ACL for an item's original (full size) file about to be uploaded to provider.
			 *
			 * @param string $acl      The canned ACL for the provider.
			 * @param array  $metadata The attachment's metadata.
			 * @param int    $id       The attachment's ID.
			 *
			 * @deprecated 2.6.0 Please use filter "as3cf_upload_object_key_as_private" instead.
			 */
			$acl = apply_filters( 'as3cf_upload_acl', $acl, $metadata, $as3cf_item->source_id() );
		} else {
			/**
			 * Determine ACL for an item's individual thumbnail size about to be uploaded to provider.
			 *
			 * @param string $acl  The canned ACL for the provider.
			 * @param string $size Size name for file (thumbnail, medium, large).
			 * @param int    $id   The attachment's ID.
			 * @param array  $data The attachment's metadata.
			 *
			 * @deprecated 2.6.0 Please use filter "as3cf_upload_object_key_as_private" instead.
			 */
			$acl = apply_filters( 'as3cf_upload_acl_sizes', $acl, $object_key, $as3cf_item->source_id(), $metadata );
		}

		if ( ! empty( $acl ) && $private_acl === $acl ) {
			return true;
		}

		return $is_private;
	}

	/**
	 * Fire legacy action just before a Media Library Item is offloaded.
	 *
	 * @handles as3cf_pre_upload_object
	 *
	 * @param Item  $as3cf_item
	 * @param array $args
	 */
	public function action_pre_upload_object( $as3cf_item, $args ) {
		if ( Media_Library_Item::source_type() !== $as3cf_item->source_type() ) {
			return;
		}

		/**
		 * Actions fires when an Item's original file might be offloaded.
		 *
		 * This action gives notice that an Item is being processed for upload to a bucket,
		 * and the given arguments represent the original file's potential offload location.
		 * However, if the current process is for picking up extra files associated with the item,
		 * the indicated original file may not actually be offloaded if it does not exist
		 * on the server but has already been offloaded.
		 *
		 * @param int                $id         The attachment id.
		 * @param Media_Library_Item $as3cf_item The Item whose files are being offloaded.
		 * @param string             $path       The path to the item.
		 * @param array              $args       The arguments that could be used to offload the original file.
		 *
		 * @deprecated 2.6.0 Please use action "as3cf_pre_upload_object" instead.
		 */
		do_action( 'as3cf_upload_attachment_pre_remove', $as3cf_item->source_id(), $as3cf_item, $as3cf_item->normalized_path_dir(), $args );
	}

	/**
	 * Takes notice that an attachment is about to be deleted and prepares for it.
	 *
	 * @handles pre_delete_attachment
	 *
	 * @param bool|null $delete Whether to go forward with deletion.
	 *
	 * @return bool|null
	 */
	public function pre_delete_attachment( $delete ) {
		if ( is_null( $delete ) ) {
			$this->deleting_attachment = true;
		}

		return $delete;
	}

	/**
	 * Takes notice that an attachment has been deleted and undoes previous preparations for the event.
	 *
	 * @handles delete_post
	 *
	 * Note: delete_post is used as there is a potential that deleted_post is not reached.
	 */
	public function delete_post() {
		$this->deleting_attachment = false;
	}
}
