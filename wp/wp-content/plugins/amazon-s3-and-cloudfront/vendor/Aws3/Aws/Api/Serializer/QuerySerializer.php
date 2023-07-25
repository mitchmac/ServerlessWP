<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\Api\Serializer;

use DeliciousBrains\WP_Offload_Media\Aws3\Aws\Api\Service;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\CommandInterface;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\EndpointV2\EndpointProviderV2;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\EndpointV2\EndpointV2SerializerTrait;
use DeliciousBrains\WP_Offload_Media\Aws3\GuzzleHttp\Psr7\Request;
use DeliciousBrains\WP_Offload_Media\Aws3\Psr\Http\Message\RequestInterface;
/**
 * Serializes a query protocol request.
 * @internal
 */
class QuerySerializer
{
    use EndpointV2SerializerTrait;
    private $endpoint;
    private $api;
    private $paramBuilder;
    public function __construct(Service $api, $endpoint, callable $paramBuilder = null)
    {
        $this->api = $api;
        $this->endpoint = $endpoint;
        $this->paramBuilder = $paramBuilder ?: new QueryParamBuilder();
    }
    /**
     * When invoked with an AWS command, returns a serialization array
     * containing "method", "uri", "headers", and "body" key value pairs.
     *
     * @param CommandInterface $command Command to serialize into a request.
     * @param $endpointProvider Provider used for dynamic endpoint resolution.
     * @param $clientArgs Client arguments used for dynamic endpoint resolution.
     *
     * @return RequestInterface
     */
    public function __invoke(CommandInterface $command, $endpointProvider = null, $clientArgs = null)
    {
        $operation = $this->api->getOperation($command->getName());
        $body = ['Action' => $command->getName(), 'Version' => $this->api->getMetadata('apiVersion')];
        $commandArgs = $command->toArray();
        // Only build up the parameters when there are parameters to build
        if ($commandArgs) {
            $body += \call_user_func($this->paramBuilder, $operation->getInput(), $commandArgs);
        }
        $body = \http_build_query($body, '', '&', \PHP_QUERY_RFC3986);
        $headers = ['Content-Length' => \strlen($body), 'Content-Type' => 'application/x-www-form-urlencoded'];
        if ($endpointProvider instanceof EndpointProviderV2) {
            $this->setRequestOptions($endpointProvider, $command, $operation, $commandArgs, $clientArgs, $headers);
        }
        return new Request('POST', $this->endpoint, $headers, $body);
    }
}
