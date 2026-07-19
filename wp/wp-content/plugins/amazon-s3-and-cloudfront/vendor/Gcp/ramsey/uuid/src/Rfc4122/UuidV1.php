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

use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Codec\CodecInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Converter\NumberConverterInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Converter\TimeConverterInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Exception\InvalidArgumentException;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Rfc4122\FieldsInterface as Rfc4122FieldsInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Uuid;
/**
 * Gregorian time, or version 1, UUIDs include timestamp, clock sequence, and node values, combined into a 128-bit unsigned integer
 *
 * @link https://www.rfc-editor.org/rfc/rfc9562#section-5.1 RFC 9562, 5.1. UUID Version 1
 *
 * @immutable
 */
final class UuidV1 extends Uuid implements UuidInterface
{
    use TimeTrait;
    /**
     * Creates a version 1 (Gregorian time) UUID
     *
     * @param Rfc4122FieldsInterface $fields The fields from which to construct a UUID
     * @param NumberConverterInterface $numberConverter The number converter to use for converting hex values to/from integers
     * @param CodecInterface $codec The codec to use when encoding or decoding UUID strings
     * @param TimeConverterInterface $timeConverter The time converter to use for converting timestamps extracted from a
     *     UUID to unix timestamps
     */
    public function __construct(Rfc4122FieldsInterface $fields, NumberConverterInterface $numberConverter, CodecInterface $codec, TimeConverterInterface $timeConverter)
    {
        if ($fields->getVersion() !== Uuid::UUID_TYPE_TIME) {
            throw new InvalidArgumentException('Fields used to create a UuidV1 must represent a version 1 (time-based) UUID');
        }
        parent::__construct($fields, $numberConverter, $codec, $timeConverter);
    }
}
