<?php

namespace DeliciousBrains\WP_Offload_Media\Gcp\Rize\UriTemplate\Node;

use DeliciousBrains\WP_Offload_Media\Gcp\Rize\UriTemplate\Parser;
/**
 * Base class for all Nodes.
 */
abstract class Abstraction
{
    public function __construct(private readonly string $token)
    {
    }
    /**
     * Expands URI template
     *
     * @param array<string, mixed> $params
     */
    public function expand(Parser $parser, array $params = []) : ?string
    {
        return $this->token;
    }
    /**
     * Matches given URI against current node.
     *
     * @param array<string, mixed> $params
     *
     * @return null|array{0: string, 1: array<string, mixed>} `uri and params` or `null` if not match and $strict is true
     */
    public function match(Parser $parser, string $uri, array $params = [], bool $strict = \false) : ?array
    {
        // match literal string from start to end
        if (\str_starts_with($uri, $this->token)) {
            $uri = \substr($uri, \strlen($this->token));
        } elseif ($strict) {
            return null;
        }
        return [$uri, $params];
    }
    public function getToken() : string
    {
        return $this->token;
    }
}
