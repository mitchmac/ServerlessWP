<?php

/**
 * This file is part of the ramsey/uuid library
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) Ben Ramsey <ben@benramsey.com>
 * @license http://opensource.org/licenses/MIT MIT
 */
declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Rfc4122;

use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Builder\UuidBuilderInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Codec\CodecInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Converter\NumberConverterInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Converter\TimeConverterInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Exception\UnableToBuildUuidException;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Exception\UnsupportedOperationException;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Nonstandard\UuidV6;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Rfc4122\UuidInterface as Rfc4122UuidInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\UuidInterface;
use Throwable;
/**
 * UuidBuilder builds instances of RFC 4122 UUIDs
 *
 * @psalm-immutable
 */
class UuidBuilder implements UuidBuilderInterface
{
    /**
     * @var NumberConverterInterface
     */
    private $numberConverter;
    /**
     * @var TimeConverterInterface
     */
    private $timeConverter;
    /**
     * Constructs the DefaultUuidBuilder
     *
     * @param NumberConverterInterface $numberConverter The number converter to
     *     use when constructing the Uuid
     * @param TimeConverterInterface $timeConverter The time converter to use
     *     for converting timestamps extracted from a UUID to Unix timestamps
     */
    public function __construct(NumberConverterInterface $numberConverter, TimeConverterInterface $timeConverter)
    {
        $this->numberConverter = $numberConverter;
        $this->timeConverter = $timeConverter;
    }
    /**
     * Builds and returns a Uuid
     *
     * @param CodecInterface $codec The codec to use for building this Uuid instance
     * @param string $bytes The byte string from which to construct a UUID
     *
     * @return Rfc4122UuidInterface UuidBuilder returns instances of Rfc4122UuidInterface
     *
     * @psalm-pure
     */
    public function build(CodecInterface $codec, string $bytes) : UuidInterface
    {
        try {
            $fields = $this->buildFields($bytes);
            if ($fields->isNil()) {
                return new NilUuid($fields, $this->numberConverter, $codec, $this->timeConverter);
            }
            switch ($fields->getVersion()) {
                case 1:
                    return new UuidV1($fields, $this->numberConverter, $codec, $this->timeConverter);
                case 2:
                    return new UuidV2($fields, $this->numberConverter, $codec, $this->timeConverter);
                case 3:
                    return new UuidV3($fields, $this->numberConverter, $codec, $this->timeConverter);
                case 4:
                    return new UuidV4($fields, $this->numberConverter, $codec, $this->timeConverter);
                case 5:
                    return new UuidV5($fields, $this->numberConverter, $codec, $this->timeConverter);
                case 6:
                    return new UuidV6($fields, $this->numberConverter, $codec, $this->timeConverter);
            }
            throw new UnsupportedOperationException('The UUID version in the given fields is not supported ' . 'by this UUID builder');
        } catch (Throwable $e) {
            throw new UnableToBuildUuidException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
    /**
     * Proxy method to allow injecting a mock, for testing
     */
    protected function buildFields(string $bytes) : FieldsInterface
    {
        return new Fields($bytes);
    }
}
