<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\ClientSideMonitoring;

use DeliciousBrains\WP_Offload_Media\Aws3\Aws\CommandInterface;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\Exception\AwsException;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\ResultInterface;
use DeliciousBrains\WP_Offload_Media\Aws3\GuzzleHttp\Psr7\Request;
use DeliciousBrains\WP_Offload_Media\Aws3\Psr\Http\Message\RequestInterface;
/**
 * @internal
 */
interface MonitoringMiddlewareInterface
{
    /**
     * Data for event properties to be sent to the monitoring agent.
     *
     * @param RequestInterface $request
     * @return array
     */
    public static function getRequestData(RequestInterface $request);
    /**
     * Data for event properties to be sent to the monitoring agent.
     *
     * @param ResultInterface|AwsException|\Exception $klass
     * @return array
     */
    public static function getResponseData($klass);
    public function __invoke(CommandInterface $cmd, RequestInterface $request);
}
