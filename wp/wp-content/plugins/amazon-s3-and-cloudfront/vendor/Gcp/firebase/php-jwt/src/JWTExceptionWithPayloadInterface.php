<?php

namespace DeliciousBrains\WP_Offload_Media\Gcp\Firebase\JWT;

interface JWTExceptionWithPayloadInterface
{
    /**
     * Get the payload that caused this exception.
     *
     * @return object
     */
    public function getPayload() : object;
    /**
     * Get the payload that caused this exception.
     *
     * @param object $payload
     * @return void
     */
    public function setPayload(object $payload) : void;
}
