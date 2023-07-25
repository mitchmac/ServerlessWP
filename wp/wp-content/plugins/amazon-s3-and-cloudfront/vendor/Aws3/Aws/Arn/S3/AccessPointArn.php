<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\Arn\S3;

use DeliciousBrains\WP_Offload_Media\Aws3\Aws\Arn\AccessPointArn as BaseAccessPointArn;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\Arn\AccessPointArnInterface;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\Arn\ArnInterface;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\Arn\Exception\InvalidArnException;
/**
 * @internal
 */
class AccessPointArn extends BaseAccessPointArn implements AccessPointArnInterface
{
    /**
     * Validation specific to AccessPointArn
     *
     * @param array $data
     */
    public static function validate(array $data)
    {
        parent::validate($data);
        if ($data['service'] !== 's3') {
            throw new InvalidArnException("The 3rd component of an S3 access" . " point ARN represents the region and must be 's3'.");
        }
    }
}
