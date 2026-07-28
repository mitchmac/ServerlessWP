<?php

namespace DeliciousBrains\WP_Offload_Media\Gcp\Google\ApiCore;

use DeliciousBrains\WP_Offload_Media\Gcp\GuzzleHttp\Psr7\Utils;
use DeliciousBrains\WP_Offload_Media\Gcp\Psr\Http\Message\UriInterface;
/**
 * @internal
 */
class InsecureRequestBuilder extends RequestBuilder
{
    /**
     * @param string $path
     * @param array $queryParams
     * @return UriInterface
     */
    protected function buildUri(string $path, array $queryParams)
    {
        $uri = Utils::uriFor(\sprintf('http://%s%s', $this->baseUri, $path));
        if ($queryParams) {
            $uri = $this->buildUriWithQuery($uri, $queryParams);
        }
        return $uri;
    }
}
