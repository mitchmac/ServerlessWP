<?php

namespace DeliciousBrains\WP_Offload_Media\Gcp\Rize;

use DeliciousBrains\WP_Offload_Media\Gcp\Rize\UriTemplate\Parser;
/**
 * URI Template.
 */
class UriTemplate
{
    protected Parser $parser;
    protected array $parsed = [];
    public function __construct(protected string $base_uri = '', protected array $params = [], ?Parser $parser = null)
    {
        $this->parser = $parser ?: $this->createNodeParser();
    }
    /**
     * Expands URI Template.
     *
     * @param mixed $params
     */
    public function expand(string $uri, $params = []) : string
    {
        $params += $this->params;
        $uri = $this->base_uri . $uri;
        $result = [];
        // quick check
        if (!\str_contains($uri, '{')) {
            return $uri;
        }
        $parser = $this->parser;
        $nodes = $parser->parse($uri);
        foreach ($nodes as $node) {
            $result[] = $node->expand($parser, $params);
        }
        return \implode('', $result);
    }
    /**
     * Extracts variables from URI.
     *
     * @return null|array params or null if not match and $strict is true
     */
    public function extract(string $template, string $uri, bool $strict = \false) : ?array
    {
        $params = [];
        $nodes = $this->parser->parse($template);
        // PHP 8.1.0RC4-dev still throws deprecation warning for `strlen`.
        // $uri    = (string) $uri;
        foreach ($nodes as $node) {
            // if strict is given, and there's no remaining uri just return null
            if ($strict && (string) $uri === '') {
                return null;
            }
            // URI will be truncated from the start when a match is found
            $match = $node->match($this->parser, $uri, $params, $strict);
            if ($match === null) {
                return null;
            }
            [$uri, $params] = $match;
        }
        // if there's remaining $uri, matching is failed
        if ($strict && (string) $uri !== '') {
            return null;
        }
        return $params;
    }
    public function getParser() : Parser
    {
        return $this->parser;
    }
    protected function createNodeParser() : Parser
    {
        static $parser;
        if ($parser) {
            return $parser;
        }
        return $parser = new Parser();
    }
}
