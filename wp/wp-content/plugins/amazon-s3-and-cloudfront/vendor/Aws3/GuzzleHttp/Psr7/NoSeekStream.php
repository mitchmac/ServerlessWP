<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Aws3\GuzzleHttp\Psr7;

use DeliciousBrains\WP_Offload_Media\Aws3\Psr\Http\Message\StreamInterface;
/**
 * Stream decorator that prevents a stream from being seeked.
 */
final class NoSeekStream implements StreamInterface
{
    use StreamDecoratorTrait;
    use NonSerializableStreamTrait;
    private StreamInterface $stream;
    public function seek(int $offset, int $whence = \SEEK_SET) : void
    {
        throw new \RuntimeException('Cannot seek a NoSeekStream');
    }
    public function isSeekable() : bool
    {
        return \false;
    }
}
