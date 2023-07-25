<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Gcp\Brick\Math\Exception;

/**
 * Base class for all math exceptions.
 *
 * This class is abstract to ensure that only fine-grained exceptions are thrown throughout the code.
 */
class MathException extends \RuntimeException
{
}
