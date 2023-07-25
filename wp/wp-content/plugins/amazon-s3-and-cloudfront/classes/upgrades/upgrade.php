<?php
/**
 * Upgrade
 *
 * @package     amazon-s3-and-cloudfront
 * @subpackage  Classes/Upgrade
 * @copyright   Copyright (c) 2014, Delicious Brains
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       0.6.2
 */

namespace DeliciousBrains\WP_Offload_Media\Upgrades;

use Amazon_S3_And_CloudFront;
use AS3CF_Utils;
use DeliciousBrains\WP_Offload_Media\Items\Media_Library_Item;
use DeliciousBrains\WP_Offload_Media\Upgrades\Exceptions\No_More_Blogs_Exception;
use DeliciousBrains\WP_Offload_Media\Upgrades\Exceptions\Batch_Limits_Exceeded_Exception;
use DeliciousBrains\WP_Offload_Media\Upgrades\Exceptions\Too_Many_Errors_Exception;
use WP_Error;

/**
 * Upgrade Class
 *
 * This class handles updates to offloaded items.
 *
 * @since 0.6.2
 */
abstract class Upgrade {

	/**
	 * @var Amazon_S3_And_CloudFront
	 */
	protected $as3cf;

	/**
	 * @var int
	 */
	protected $upgrade_id = 0;

	/**
	 * @var string
	 */
	protected $upgrade_name = 'base';

	/**
	 * @var string 'metadata', 'attachment'
	 */
	protected $upgrade_type = 'attachment';

	/**
	 * @var string
	 */
	protected $running_update_text;

	/**
	 * @var string
	 */
	protected $settings_key = 'post_meta_version';

	/**
	 * @var string
	 */
	protected $cron_hook;

	/**
	 * @var string
	 */
	protected $cron_schedule_key;

	/**
	 * @var mixed|void
	 */
	protected $cron_interval_in_minutes;

	/**
	 * @var mixed|void
	 */
	protected $error_threshold;

	/**
	 * @var int
	 */
	protected $error_count;

	/**
	 * @var string
	 */
	public static $lock_key = 'as3cf_upgrade_lock';

	/**
	 * @var int Time limit in seconds.
	 */
	protected $time_limit = 20;

	/**
	 * @var int Batch size limit for this request session.
	 */
	protected $size_limit = 500;

	/**
	 * @var int Finish time
	 */
	protected $finish;

	/**
	 * @var int Maximum number of items to be processed in a single request.
	 */
	protected $max_items_processable;

	/**
	 * @var int Number of items processed.
	 */
	protected $items_processed;

	/**
	 * @var mixed Last item processed.
	 */
	protected $last_item;

	/**
	 * @var int The current blog ID.
	 */
	protected $blog_id;

	/**
	 * @var string The wpdb prefix for the current blog.
	 */
	protected $blog_prefix;

	/**
	 * @var int The last completed blog ID.
	 */
	protected $last_blog_id;

	/**
	 * @var array Blog IDs which are already processed.
	 */
	protected $processed_blogs_ids;

	/**
	 * @var array Session data
	 */
	protected $session;

	const STATUS_RUNNING = 1;
	const STATUS_ERROR   = 2;
	const STATUS_PAUSED  = 3;

	/**
	 * Start it up
	 *
	 * @param Amazon_S3_And_CloudFront $as3cf - the instance of the as3cf class
	 */
	public function __construct( $as3cf ) {
		$this->as3cf = $as3cf;

		$this->running_update_text = $this->get_running_update_text();
		$this->cron_hook           = 'as3cf_cron_update_' . $this->upgrade_name;
		$this->cron_schedule_key   = 'as3cf_update_' . $this->upgrade_name . '_interval';

		$this->cron_interval_in_minutes = apply_filters( 'as3cf_update_' . $this->upgrade_name . '_interval', 2 );
		$this->error_threshold          = apply_filters( 'as3cf_update_' . $this->upgrade_name . '_error_threshold', 20 );
		$this->max_items_processable    = apply_filters( 'as3cf_update_' . $this->upgrade_name . '_batch_size', $this->size_limit );

		if ( $this->is_completed() ) {
			return;
		}

		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );  // phpcs:ignore WordPress.WP.CronInterval
		add_action( $this->cron_hook, array( $this, 'do_upgrade' ) );

		add_action( 'admin_init', array( $this, 'maybe_handle_action' ) );

		// Each upgrade potentially has a unique settings locked notification.
		add_filter( 'as3cf_get_upgrade_locked_notifications', array( $this, 'get_locked_notifications' ) );
		add_filter( 'as3cf_get_running_upgrade', array( $this, 'get_running_upgrade' ) );

		// Do default checks if the upgrade can be started
		if ( $this->maybe_init() ) {
			$this->init();
		}
	}

	/**
	 * Can we start the upgrade using default checks
	 *
	 * @return bool
	 */
	protected function maybe_init() {
		if ( AS3CF_Utils::is_ajax() ) {
			return false;
		}

		if ( ! $this->screen_can_init() ) {
			return false;
		}

		if ( ! $this->as3cf->is_plugin_setup( true ) ) {
			return false;
		}

		if ( $this->is_completed() ) {
			return false;
		}

		if ( ! $this->has_previous_upgrade_completed() ) {
			return false;
		}

		if ( $this->is_locked() ) {
			return false;
		}

		// If the upgrade status is already set, then we've already initialized the upgrade
		if ( $this->get_upgrade_status() ) {
			if ( $this->is_running() ) {
				// Make sure cron job is persisted in case it has dropped
				$this->schedule();
			} else {
				// Refresh the lock to stop anything from interfering while paused.
				$this->lock_upgrade();
			}

			return false;
		}

		return true;
	}

	/**
	 * Count items left to process for the current blog.
	 *
	 * @return int
	 */
	protected function count_items_to_process() {
		return count( $this->get_items_to_process( $this->blog_prefix, false, $this->last_item ) );
	}

	/**
	 * Get items to process.
	 *
	 * @param string     $prefix
	 * @param int        $limit
	 * @param bool|mixed $offset
	 *
	 * @return array
	 */
	abstract protected function get_items_to_process( $prefix, $limit, $offset = false );

	/**
	 * Upgrade item.
	 *
	 * @param mixed $item
	 *
	 * @return bool
	 */
	abstract protected function upgrade_item( $item );

	/**
	 * Get running update text.
	 *
	 * @return string
	 */
	abstract protected function get_running_update_text();

	/**
	 * Fire up the upgrade
	 */
	protected function init() {
		// Initialize the upgrade
		$this->save_session( array( 'status' => self::STATUS_RUNNING ) );

		$this->schedule();
	}

	/**
	 * WP Cron callback to run the upgrade.
	 */
	public function do_upgrade() {
		$this->lock_upgrade();
		$this->start_timer();

		if ( $this->is_completed() || ! $this->is_running() ) {
			$this->unschedule();

			return;
		}

		$this->boot_session();
		$this->run_upgrade();
	}

	/**
	 * Run or resume the main upgrade process.
	 */
	protected function run_upgrade() {
		try {
			$blog_id = $this->get_initial_blog_id();

			do {
				$this->switch_to_blog( $blog_id );
				$this->check_batch_limits();

				if ( $this->upgrade_blog() ) {
					$this->blog_upgrade_completed();
				} else {

					// If the blog didn't complete, break and try again next time before moving on.
					break;
				}
			} while ( $blog_id = $this->next_blog_id() ); // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		} catch ( No_More_Blogs_Exception $e ) {
			/*
			 * The upgrade is complete when there are no more blogs left to finish.
			 */
			$this->upgrade_finished();

			return;
		} catch ( Too_Many_Errors_Exception $e ) {
			$this->upgrade_error();

			return;
		} catch ( Batch_Limits_Exceeded_Exception $e ) { //phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch

			// Save the session and finish this round right away.
		}

		$this->close_session();
		$this->save_session( $this->session );
	}

	/**
	 * Upgrade the current blog.
	 *
	 * @return bool true if all items for the blog were upgraded, otherwise false.
	 * @throws Batch_Limits_Exceeded_Exception
	 *
	 * @throws Too_Many_Errors_Exception
	 */
	protected function upgrade_blog() {
		$total    = $this->count_items_to_process();
		$items    = $this->blog_batch_items();
		$upgraded = 0;

		foreach ( $items as $item ) {
			if ( $this->upgrade_item( $item ) ) {
				$this->item_upgrade_completed( $item );
				$upgraded++;
			}

			// Items always count towards processing limits.
			$this->items_processed++;

			$this->check_batch_limits();
		}

		/*
		 * If the number upgraded is the same as the remaining total to process
		 * then all items have been upgraded for this blog.
		 */
		if ( $upgraded === (int) $total ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the next sequential blog ID if there is one.
	 *
	 * @return int
	 * @throws No_More_Blogs_Exception
	 */
	protected function next_blog_id() {
		$blog_id = $this->blog_id ? $this->blog_id : $this->last_blog_id;

		do {
			$blog_id--;

			if ( $blog_id < 1 ) {
				throw new No_More_Blogs_Exception();
			}
		} while ( ! $this->is_blog_processable( $blog_id ) );

		return $blog_id;
	}

	/**
	 * Get the maximum number of processable items for the current blog,
	 * limited by the remaining number of items possible to process for this request.
	 *
	 * @return array
	 */
	protected function blog_batch_items() {
		$limit = $this->max_items_processable - $this->items_processed;

		return $this->get_items_to_process( $this->blog_prefix, $limit, $this->last_item );
	}

	/**
	 * Adds notices about issues with upgrades allowing user to restart them.
	 */
	public function maybe_display_notices() {
		$action_url = $this->as3cf->get_plugin_page_url( array(
			'action' => 'restart_update',
			'update' => $this->upgrade_name,
		), 'self' );
		$msg_type   = 'notice-info';
		$custom_id  = 'as3cf-upgrade-notice-' . $this->upgrade_name;

		switch ( $this->get_upgrade_status() ) {
			case self::STATUS_RUNNING:
				$msg         = $this->get_running_message();
				$action_text = __( 'Pause Update', 'amazon-s3-and-cloudfront' );
				$action_url  = $this->as3cf->get_plugin_page_url( array(
					'action' => 'pause_update',
					'update' => $this->upgrade_name,
				), 'self' );
				break;
			case self::STATUS_PAUSED:
				$msg         = $this->get_paused_message();
				$action_text = __( 'Restart Update', 'amazon-s3-and-cloudfront' );
				break;
			case self::STATUS_ERROR:
				$msg         = $this->get_error_message();
				$action_text = __( 'Try To Run It Again', 'amazon-s3-and-cloudfront' );
				$msg_type    = 'error';
				break;
			default:
				$this->as3cf->notices->remove_notice_by_id( $custom_id );

				return;
		}

		$msg .= ' <strong><a href="' . $action_url . '">' . $action_text . '</a></strong>';

		$args = array(
			'custom_id'             => $custom_id,
			'type'                  => $msg_type,
			'dismissible'           => false,
			'only_show_in_settings' => true,
		);

		$this->as3cf->notices->add_notice( $msg, $args );
	}

	/**
	 * Get running message.
	 *
	 * @return string
	 */
	protected function get_running_message() {
		return sprintf( __( '<strong>Running %1$s Update%2$s</strong> &mdash; We&#8217;re going through all the offloaded Media Library items %3$s This will be done quietly in the background, processing a small batch of Media Library items every %4$d minutes. There should be no noticeable impact on your server&#8217;s performance.', 'amazon-s3-and-cloudfront' ),
			ucwords( $this->upgrade_type ),
			$this->get_progress_text(),
			$this->running_update_text,
			$this->cron_interval_in_minutes
		);
	}

	/**
	 * Get paused message.
	 *
	 * @return string
	 */
	protected function get_paused_message() {
		return sprintf( __( '<strong>%1$s Update Paused%2$s</strong> &mdash; Updating Media Library %3$s has been paused.', 'amazon-s3-and-cloudfront' ),
			ucwords( $this->upgrade_type ),
			$this->get_progress_text(),
			$this->upgrade_type
		);
	}

	/**
	 * Get error message.
	 *
	 * @return string
	 */
	protected function get_error_message() {
		return sprintf( __( '<strong>Error Updating %1$s</strong> &mdash; We ran into some errors attempting to update the %2$s for all your Media Library items that have been offloaded. Please check your error log for details. (#%3$d)', 'amazon-s3-and-cloudfront' ),
			ucwords( $this->upgrade_type ),
			$this->upgrade_type,
			$this->upgrade_id
		);
	}

	/**
	 * Get progress text.
	 *
	 * @return string
	 */
	protected function get_progress_text() {
		$progress = $this->calculate_progress();

		if ( false === $progress ) {
			// Progress can not be calculated, return
			return '';
		}

		if ( $progress > 100 ) {
			$progress = 100;
		}

		return sprintf( __( ' (%s%% Complete)', 'amazon-s3-and-cloudfront' ), $progress );
	}

	/**
	 * Calculate progress.
	 *
	 * @return bool|float
	 * @throws Batch_Limits_Exceeded_Exception
	 */
	protected function calculate_progress() {
		$this->boot_session();

		if ( is_multisite() ) {
			$all_blog_ids = AS3CF_Utils::get_blog_ids();
			$decimal      = count( $this->processed_blogs_ids ) / count( $all_blog_ids );
		} else {
			// Set up any per-site state
			$this->switch_to_blog( get_current_blog_id() );
			$counts = Media_Library_Item::count_items();

			// If there are no items, disable progress calculation
			// and protect against division by zero.
			if ( ! $counts['total'] ) {
				return false;
			}

			$remaining = $this->count_items_to_process();
			$decimal   = ( $counts['total'] - $remaining ) / $counts['total'];
		}

		return round( $decimal * 100, 2 );
	}

	/**
	 * Handler for the running upgrade actions
	 */
	public function maybe_handle_action() {
		if ( ! isset( $_GET['page'] ) || sanitize_key( $_GET['page'] ) !== $this->as3cf->get_plugin_slug() ) { // input var okay
			return;
		}

		if ( ! isset( $_GET['action'] ) ) {
			return;
		}

		if ( ! isset( $_GET['update'] ) || sanitize_key( $_GET['update'] ) !== $this->upgrade_name ) { // input var okay
			return;
		}

		$method_name = 'action_' . sanitize_key( $_GET['action'] ); // input var okay

		if ( method_exists( $this, $method_name ) ) {
			call_user_func( array( $this, $method_name ) );
		}
	}

	/**
	 * Exit upgrade with an error
	 */
	protected function upgrade_error() {
		$this->close_session();
		$this->session['status'] = self::STATUS_ERROR;
		$this->save_session( $this->session );
		$this->unschedule();
	}

	/**
	 * Complete the upgrade
	 */
	protected function upgrade_finished() {
		$this->clear_session();
		$this->update_saved_upgrade_id();
		$this->unlock_upgrade();
		$this->unschedule();
	}

	/**
	 * Restart upgrade
	 */
	protected function action_restart_update() {
		$this->init();
		$this->end_action();
	}

	/**
	 * Pause upgrade
	 */
	protected function action_pause_update() {
		$this->unschedule();

		if ( $this->is_running() ) {
			$this->change_status_request( self::STATUS_PAUSED );
		}

		$this->end_action();
	}

	/**
	 * Common function for ending an action in a consistent way.
	 */
	private function end_action() {
		// Make sure notices reflect new status.
		$this->maybe_display_notices();

		$url = $this->as3cf->get_plugin_page_url( array(), 'self' );
		wp_redirect( $url );
		exit;
	}

	/**
	 * Helper for the above action requests
	 *
	 * @param int $status
	 */
	protected function change_status_request( $status ) {
		$session           = $this->get_session();
		$session['status'] = $status;
		$this->save_session( $session );
	}

	/**
	 * Schedule the cron
	 */
	protected function schedule() {
		$this->as3cf->schedule_event( $this->cron_hook, $this->cron_schedule_key );
	}

	/**
	 * Remove the cron schedule
	 */
	protected function unschedule() {
		$this->as3cf->clear_scheduled_event( $this->cron_hook );
	}

	/**
	 * Add custom cron interval schedules
	 *
	 * @param array $schedules
	 *
	 * @return array
	 */
	public function cron_schedules( $schedules ) {
		// Add the upgrade interval to the existing schedules.
		$schedules[ $this->cron_schedule_key ] = array(
			'interval' => $this->cron_interval_in_minutes * 60,
			'display'  => sprintf( __( 'Every %d Minutes', 'amazon-s3-and-cloudfront' ), $this->cron_interval_in_minutes ),
		);

		return $schedules;
	}

	/**
	 * Get the current status of the upgrade
	 * See STATUS_* constants in the class declaration above.
	 */
	protected function get_upgrade_status() {
		$session = $this->get_session();

		if ( ! isset( $session['status'] ) ) {
			return '';
		}

		return $session['status'];
	}

	/**
	 * Retrieve session data from plugin settings
	 *
	 * @return array
	 */
	protected function get_session() {
		return get_site_option( 'as3cf_update_' . $this->upgrade_name . '_session', array() );
	}

	/**
	 * Store data to be used between requests in plugin settings
	 *
	 * @param array $session session data to store
	 */
	protected function save_session( $session ) {
		update_site_option( 'as3cf_update_' . $this->upgrade_name . '_session', $session );
	}

	/**
	 * Remove the session data to be used between requests
	 */
	protected function clear_session() {
		delete_site_option( 'as3cf_update_' . $this->upgrade_name . '_session' );
	}

	/**
	 * Get the saved upgrade ID
	 *
	 * @return int|mixed|string|WP_Error
	 */
	protected function get_saved_upgrade_id() {
		return $this->as3cf->get_setting( $this->settings_key, 0 );
	}

	/**
	 * Update the saved upgrade ID
	 */
	protected function update_saved_upgrade_id() {
		$this->as3cf->set_setting( $this->settings_key, $this->upgrade_id );
		$this->as3cf->save_settings();
	}

	/**
	 * Has previous upgrade completed
	 *
	 * @return bool
	 */
	protected function has_previous_upgrade_completed() {
		// Has the previous upgrade completed yet?
		$previous_id = $this->upgrade_id - 1;
		if ( 0 !== $previous_id && (int) $this->get_saved_upgrade_id() < $previous_id ) {
			// Previous still running, abort
			return false;
		}

		return true;
	}

	/**
	 * Lock upgrade.
	 */
	protected function lock_upgrade() {
		set_site_transient( static::$lock_key, $this->upgrade_id, MINUTE_IN_SECONDS * 3 );
	}

	/**
	 * Unlock the upgrade.
	 *
	 * Voids the lock after 1 second rather than deleting to avoid a race condition.
	 */
	protected function unlock_upgrade() {
		set_site_transient( static::$lock_key, $this->upgrade_id, 1 );
	}

	/**
	 * Whether or not the upgrade lock has been set.
	 *
	 * @return bool
	 */
	public static function is_locked() {
		return false !== get_site_transient( static::$lock_key );
	}

	/**
	 * Whether this upgrade has been completed or not.
	 *
	 * @return bool
	 */
	protected function is_completed() {
		return $this->get_saved_upgrade_id() >= $this->upgrade_id;
	}

	/**
	 * Whether this upgrade is currently running or not.
	 *
	 * @return bool
	 */
	protected function is_running() {
		return self::STATUS_RUNNING === $this->get_upgrade_status();
	}

	/**
	 * Whether this upgrade is currently paused or not.
	 *
	 * @return bool
	 */
	protected function is_paused() {
		return self::STATUS_PAUSED === $this->get_upgrade_status();
	}

	/**
	 * Set the time when the upgrade must finish by.
	 */
	protected function start_timer() {
		$this->finish = time() + apply_filters( 'as3cf_update_' . $this->upgrade_name . '_time_limit', $this->time_limit );
	}

	/**
	 * Check to see if batch limits have been exceeded.
	 *
	 * @throws Batch_Limits_Exceeded_Exception
	 * @throws Too_Many_Errors_Exception
	 */
	protected function check_batch_limits() {
		if ( $this->error_count > $this->error_threshold ) {
			throw new Too_Many_Errors_Exception();
		}

		if ( $this->items_processed > $this->max_items_processable ) {
			throw new Batch_Limits_Exceeded_Exception( 'Item limit reached.' );
		}

		if ( time() > $this->finish ) {
			throw new Batch_Limits_Exceeded_Exception( 'Time limit exceeded.' );
		}

		if ( $this->as3cf->memory_exceeded( 'as3cf_update_' . $this->upgrade_name . '_memory_exceeded' ) ) {
			throw new Batch_Limits_Exceeded_Exception( 'Memory limit exceeded with ' . memory_get_usage( true ) / 1024 / 1024 . 'MB' );
		}
	}

	/**
	 * Check if a blog exists for the given blog ID.
	 *
	 * @param int $blog_id
	 *
	 * @return bool
	 */
	protected function blog_exists( $blog_id ) {
		static $all_ids;

		if ( function_exists( 'get_site' ) ) {
			return (bool) get_site( $blog_id );
		}

		if ( ! $all_ids ) {
			$all_ids = AS3CF_Utils::get_blog_ids();
		}

		return in_array( $blog_id, $all_ids );
	}

	/**
	 * Get the largest blog ID on the network.
	 *
	 * @return null|string
	 */
	protected function get_final_blog_id() {
		global $wpdb;

		if ( is_multisite() ) {
			return $wpdb->get_var( "SELECT MAX(blog_id) FROM {$wpdb->blogs}" );
		}

		return 1;
	}

	/**
	 * Get the initial blog ID to start iterating with.
	 *
	 * @return int
	 */
	protected function get_initial_blog_id() {
		if ( $this->last_blog_id ) {
			return $this->next_blog_id();
		}

		return (int) $this->get_final_blog_id();
	}

	/**
	 * Whether the given blog ID is processable or not.
	 *
	 * @param int $blog_id
	 *
	 * @return bool
	 */
	protected function is_blog_processable( $blog_id ) {
		if ( in_array( $blog_id, $this->processed_blogs_ids ) ) {
			return false;
		}

		return $this->blog_exists( $blog_id );
	}

	/**
	 * Populate the session properties from the saved state.
	 */
	protected function boot_session() {
		$this->session             = $this->get_session();
		$this->last_blog_id        = $this->load_last_blog_id();
		$this->processed_blogs_ids = $this->load_processesed_blog_ids();
		$this->error_count         = isset( $this->session['error_count'] ) ? $this->session['error_count'] : 0;
		$this->last_item           = $this->load_last_item();
	}

	/**
	 * Get all of the processed blog IDs from the session.
	 *
	 * @return array
	 */
	protected function load_processesed_blog_ids() {
		$session = $this->session ? $this->session : $this->get_session();

		return isset( $session['processed_blog_ids'] ) ? $session['processed_blog_ids'] : array();
	}

	/**
	 * Mark the current blog upgrade as complete.
	 */
	protected function blog_upgrade_completed() {
		$this->last_blog_id          = $this->blog_id;
		$this->processed_blogs_ids[] = $this->blog_id;
		$this->last_item             = false;
	}

	/**
	 * Perform any actions necessary after the given item is completed.
	 *
	 * @param mixed $item
	 */
	protected function item_upgrade_completed( $item ) {
		$this->last_item = $item;
	}

	/**
	 * Prepare the session to be persisted.
	 */
	protected function close_session() {
		$this->session['last_blog_id']       = $this->last_blog_id;
		$this->session['offset']             = $this->last_item;
		$this->session['error_count']        = $this->error_count;
		$this->session['processed_blog_ids'] = $this->processed_blogs_ids;
	}

	/**
	 * Load the last completed blog ID from the session.
	 *
	 * @return bool|int
	 */
	protected function load_last_blog_id() {
		if ( ! empty( $this->session['last_blog_id'] ) ) {
			return (int) $this->session['last_blog_id'];
		}

		return null;
	}

	/**
	 * Switch to the given blog, and update blog-specific state.
	 *
	 * @param int $blog_id
	 *
	 * @throws Batch_Limits_Exceeded_Exception
	 */
	protected function switch_to_blog( $blog_id ) {
		$this->as3cf->switch_to_blog( $blog_id );
		$this->blog_id     = (int) $blog_id;
		$this->blog_prefix = $GLOBALS['wpdb']->prefix;
	}

	/**
	 * Get the last processed item from the session.
	 *
	 * @return bool|mixed
	 */
	protected function load_last_item() {
		return isset( $this->session['offset'] ) ? $this->session['offset'] : false;
	}

	/**
	 * Whether or not the current screen can initialize the upgrade.
	 *
	 * @return bool
	 */
	protected function screen_can_init() {
		if ( is_multisite() ) {
			return is_network_admin();
		}

		return is_admin();
	}

	/**
	 * Get description for locked notification.
	 *
	 * @return string
	 */
	public function get_locked_notification() {
		return sprintf(
			__(
				'<strong>Settings Locked</strong> &mdash; You can\'t change any of your settings until the "%s" update has completed.',
				'amazon-s3-and-cloudfront'
			),
			ucwords( $this->upgrade_type )
		);
	}

	/**
	 * Append this upgrade's locked notification text to an array, and maybe show the upgrade status notice.
	 *
	 * @handles as3cf_get_upgrade_locked_notifications
	 *
	 * @param array $notifications
	 *
	 * @return array
	 */
	public function get_locked_notifications( array $notifications ) {
		$this->maybe_display_notices();

		$notifications[ $this->upgrade_name ] = $this->get_locked_notification();

		return $notifications;
	}

	/**
	 * Returns upgrade's name if it is running, paused or locked (usually due to errors if not running or paused).
	 *
	 * @handles as3cf_get_running_upgrade
	 *
	 * @param string $running_upgrade
	 *
	 * @return string
	 */
	public function get_running_upgrade( $running_upgrade ) {
		if ( empty( $running_upgrade ) && ( $this->is_running() || $this->is_paused() || $this->is_locked() ) ) {
			return $this->upgrade_name;
		}

		return $running_upgrade;
	}
}
