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
namespace DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Converter\Time;

use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Converter\TimeConverterInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Math\BrickMathCalculator;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Type\Hexadecimal;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Type\Time;
/**
 * Previously used to integrate moontoast/math as a bignum arithmetic library,
 * BigNumberTimeConverter is deprecated in favor of GenericTimeConverter
 *
 * @deprecated Transition to {@see GenericTimeConverter}.
 *
 * @psalm-immutable
 */
class BigNumberTimeConverter implements TimeConverterInterface
{
    /**
     * @var TimeConverterInterface
     */
    private $converter;
    public function __construct()
    {
        $this->converter = new GenericTimeConverter(new BrickMathCalculator());
    }
    public function calculateTime(string $seconds, string $microseconds) : Hexadecimal
    {
        return $this->converter->calculateTime($seconds, $microseconds);
    }
    public function convertTime(Hexadecimal $uuidTimestamp) : Time
    {
        return $this->converter->convertTime($uuidTimestamp);
    }
}
