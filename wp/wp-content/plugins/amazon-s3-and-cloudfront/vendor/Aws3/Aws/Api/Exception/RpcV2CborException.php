<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\Api\Exception;

use DeliciousBrains\WP_Offload_Media\Aws3\Aws\HasMonitoringEventsTrait;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\MonitoringEventsInterface;
class RpcV2CborException extends \RuntimeException implements MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
