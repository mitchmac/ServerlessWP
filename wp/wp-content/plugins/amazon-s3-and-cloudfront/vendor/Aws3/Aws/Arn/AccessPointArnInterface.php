<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\Arn;

/**
 * @internal
 */
interface AccessPointArnInterface extends ArnInterface
{
    public function getAccesspointName();
}
