<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\Endpoint\UseFipsEndpoint\Exception;

use DeliciousBrains\WP_Offload_Media\Aws3\Aws\HasMonitoringEventsTrait;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\MonitoringEventsInterface;
/**
 * Represents an error interacting with configuration for useFipsRegion
 */
class ConfigurationException extends \RuntimeException implements MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
