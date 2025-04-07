<?php

/*
 * Copyright 2023 Google LLC
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 *
 *     * Redistributions of source code must retain the above copyright
 * notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above
 * copyright notice, this list of conditions and the following disclaimer
 * in the documentation and/or other materials provided with the
 * distribution.
 *     * Neither the name of Google Inc. nor the names of its
 * contributors may be used to endorse or promote products derived from
 * this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */
namespace DeliciousBrains\WP_Offload_Media\Gcp\Google\ApiCore\Options\TransportOptions;

use ArrayAccess;
use Closure;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\ApiCore\Options\OptionsTrait;
/**
 * The RestTransportOptions class provides typing to the associative array of options used to
 * configure {@see \Google\ApiCore\Transport\RestTransport}.
 */
class RestTransportOptions implements ArrayAccess
{
    use OptionsTrait;
    private ?Closure $httpHandler;
    private ?Closure $clientCertSource;
    private ?string $restClientConfigPath;
    /**
     * @param array $options {
     *    Config options used to construct the REST transport.
     *
     *    @type callable $httpHandler
     *          A handler used to deliver PSR-7 requests.
     *    @type callable $clientCertSource
     *          A callable which returns the client cert as a string.
     *    @type string $restClientConfigPath
     *          The path to the REST client config file.
     * }
     */
    public function __construct(array $options)
    {
        $this->fromArray($options);
    }
    /**
     * Sets the array of options as class properites.
     *
     * @param array $arr See the constructor for the list of supported options.
     */
    private function fromArray(array $arr) : void
    {
        $this->setHttpHandler($arr['httpHandler'] ?? null);
        $this->setClientCertSource($arr['clientCertSource'] ?? null);
        $this->setRestClientConfigPath($arr['restClientConfigPath'] ?? null);
    }
    /**
     * @param ?callable $httpHandler
     */
    public function setHttpHandler(?callable $httpHandler)
    {
        if (!\is_null($httpHandler)) {
            $httpHandler = Closure::fromCallable($httpHandler);
        }
        $this->httpHandler = $httpHandler;
    }
    /**
     * @param ?callable $clientCertSource
     */
    public function setClientCertSource(?callable $clientCertSource)
    {
        if (!\is_null($clientCertSource)) {
            $clientCertSource = Closure::fromCallable($clientCertSource);
        }
        $this->clientCertSource = $clientCertSource;
    }
    /**
     * @param ?string $restClientConfigPath
     */
    public function setRestClientConfigPath(?string $restClientConfigPath)
    {
        $this->restClientConfigPath = $restClientConfigPath;
    }
}
