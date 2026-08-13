<?php
/**
 * Registers Akismet abilities with the WordPress Abilities API.
 *
 * @package Akismet
 * @since 5.7
 */

declare( strict_types = 1 );

// Load ability interface and classes.
require_once __DIR__ . '/abilities/interface-akismet-ability.php';
require_once __DIR__ . '/abilities/class-akismet-ability.php';
require_once __DIR__ . '/abilities/class-akismet-ability-get-stats.php';
require_once __DIR__ . '/abilities/class-akismet-ability-comment-check.php';

/**
 * Class Akismet_Abilities
 *
 * Registers Akismet abilities with the WordPress Abilities API.
 * Provides abilities for spam detection and comment moderation.
 */
class Akismet_Abilities {

	/**
	 * The category slug for Akismet abilities.
	 *
	 * @var string
	 */
	const CATEGORY_SLUG = 'akismet';

	/**
	 * Initialize the ability registration.
	 *
	 * @return void
	 */
	public static function init() {
		// Register category.
		if ( did_action( 'wp_abilities_api_categories_init' ) ) {
			self::register_category();
		} else {
			add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		}

		// Register abilities.
		if ( did_action( 'wp_abilities_api_init' ) ) {
			self::register_abilities();
		} else {
			add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
		}
	}

	/**
	 * Register the Akismet ability category.
	 *
	 * @return void
	 */
	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY_SLUG,
			array(
				'label'       => 'Akismet',
				'description' => __( 'Abilities for spam protection and comment moderation with Akismet.', 'akismet' ),
			)
		);
	}

	/**
	 * Register all Akismet abilities.
	 *
	 * @return void
	 */
	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$abilities = array(
			Akismet_Ability_Get_Stats::class,
			Akismet_Ability_Comment_Check::class,
		);

		foreach ( $abilities as $ability_class ) {
			new $ability_class();
		}
	}
}
