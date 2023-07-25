<?php

namespace DeliciousBrains\WP_Offload_Media\Providers\Delivery;

/**
 * Class Other
 *
 * This is a fallback CDN where the user can add their own name.
 *
 * @package DeliciousBrains\WP_Offload_Media\Providers\Delivery
 */
class Other extends Delivery_Provider {

	/**
	 * Can the displayed provider service name be overridden by the user?
	 *
	 * @var bool
	 */
	protected static $provider_service_name_override_allowed = true;

	/**
	 * @var string
	 */
	protected static $provider_name = 'Other';

	/**
	 * @var string
	 */
	protected static $provider_short_name = 'Other';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $provider_key_name = 'other';

	/**
	 * @var string
	 */
	protected static $service_name = 'Unknown';

	/**
	 * @var string
	 */
	protected static $service_short_name = 'Unknown';

	/**
	 * Used in filters and settings.
	 *
	 * @var string
	 */
	protected static $service_key_name = 'unknown';

	/**
	 * Optional override of "Provider Name" + "Service Name" for friendly name for service.
	 *
	 * @var string
	 */
	protected static $provider_service_name = 'Other';
}
