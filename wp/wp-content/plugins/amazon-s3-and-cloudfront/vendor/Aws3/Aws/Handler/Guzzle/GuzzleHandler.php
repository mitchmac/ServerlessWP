<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\Handler\Guzzle;

use DeliciousBrains\WP_Offload_Media\Aws3\Aws\Handler\HttpHandlerError;
use DeliciousBrains\WP_Offload_Media\Aws3\GuzzleHttp\Utils;
use DeliciousBrains\WP_Offload_Media\Aws3\GuzzleHttp\Promise;
use DeliciousBrains\WP_Offload_Media\Aws3\GuzzleHttp\Client;
use DeliciousBrains\WP_Offload_Media\Aws3\GuzzleHttp\ClientInterface;
use DeliciousBrains\WP_Offload_Media\Aws3\GuzzleHttp\TransferStats;
use DeliciousBrains\WP_Offload_Media\Aws3\Psr\Http\Message\RequestInterface as Psr7Request;
/**
 * A request handler that sends PSR-7-compatible requests with Guzzle.
 */
class GuzzleHandler
{
    /** @var ClientInterface */
    private $client;
    /**
     * @param ClientInterface $client
     */
    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?: new Client();
    }
    /**
     * @param Psr7Request $request
     * @param array       $options
     *
     * @return Promise\PromiseInterface
     */
    public function __invoke(Psr7Request $request, array $options = [])
    {
        $request = $request->withHeader('User-Agent', $request->getHeaderLine('User-Agent') . ' ' . Utils::defaultUserAgent());
        return $this->client->sendAsync($request, $this->parseOptions($options))->otherwise(static function (\Throwable $e) {
            return new Promise\RejectedPromise(['exception' => $e, 'connection_error' => HttpHandlerError::isConnectionError($e), 'response' => HttpHandlerError::getResponse($e)]);
        });
    }
    private function parseOptions(array $options)
    {
        if (isset($options['http_stats_receiver'])) {
            $fn = $options['http_stats_receiver'];
            unset($options['http_stats_receiver']);
            $prev = isset($options['on_stats']) ? $options['on_stats'] : null;
            $options['on_stats'] = static function (TransferStats $stats) use($fn, $prev) {
                if (\is_callable($prev)) {
                    $prev($stats);
                }
                $transferStats = ['total_time' => $stats->getTransferTime()];
                $transferStats += $stats->getHandlerStats();
                $fn($transferStats);
            };
        }
        return $options;
    }
}
