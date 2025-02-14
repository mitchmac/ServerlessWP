<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\Identity;

/**
 * Denotes the use of standard AWS credentials.
 *
 * @internal
 */
abstract class AwsCredentialIdentity implements IdentityInterface
{
    /**
     * Returns a UNIX timestamp, if available, representing the expiration
     * time of the AWS Credential object. Returns null if no expiration is provided.
     *
     * @return int|null
     */
    public abstract function getExpiration();
}
