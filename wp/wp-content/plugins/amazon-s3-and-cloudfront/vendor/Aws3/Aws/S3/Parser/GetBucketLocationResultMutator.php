<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\S3\Parser;

use DeliciousBrains\WP_Offload_Media\Aws3\Aws\CommandInterface;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\ResultInterface;
use DeliciousBrains\WP_Offload_Media\Aws3\Psr\Http\Message\ResponseInterface;
/**
 * A custom mutator for a GetBucketLocation request, which
 * extract the bucket location value and injects it into the
 * result as the `LocationConstraint` field.
 *
 * @internal
 */
final class GetBucketLocationResultMutator implements S3ResultMutator
{
    /**
     * @inheritDoc
     */
    public function __invoke(ResultInterface $result, CommandInterface $command, ResponseInterface $response) : ResultInterface
    {
        if ($command->getName() !== 'GetBucketLocation') {
            return $result;
        }
        static $location = 'us-east-1';
        static $pattern = '/>(.+?)<\\/LocationConstraint>/';
        if (\preg_match($pattern, $response->getBody(), $matches)) {
            $location = $matches[1] === 'EU' ? 'eu-west-1' : $matches[1];
        }
        $result['LocationConstraint'] = $location;
        $response->getBody()->rewind();
        return $result;
    }
}
