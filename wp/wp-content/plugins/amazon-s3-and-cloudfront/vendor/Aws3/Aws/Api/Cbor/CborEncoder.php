<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\Api\Cbor;

use DeliciousBrains\WP_Offload_Media\Aws3\Aws\Api\Cbor\Exception\CborException;
/**
 * Encodes PHP values to Concise Binary Object Representation according to RFC 8949
 * https://www.rfc-editor.org/rfc/rfc8949.html
 *
 * Supports Major types 0-7 including:
 * - Type 0: Unsigned integers
 * - Type 1: Negative integers
 * - Type 2: Byte strings (via ['__cbor_bytes' => $data] wrappers)
 * - Type 3: Text strings (UTF-8)
 * - Type 4: Arrays
 * - Type 5: Maps
 * - Type 6: Tagged values (timestamps)
 * - Type 7: Simple values (null, bool, float)
 *
 * @internal
 */
final class CborEncoder
{
    /**
     * Pre-encoded integers 0-23 (single byte) and common larger values
     * CBOR major type 0 (unsigned integer)
     */
    private const INT_CACHE = [0 => "\x00", 1 => "\x01", 2 => "\x02", 3 => "\x03", 4 => "\x04", 5 => "\x05", 6 => "\x06", 7 => "\x07", 8 => "\x08", 9 => "\t", 10 => "\n", 11 => "\v", 12 => "\f", 13 => "\r", 14 => "\x0e", 15 => "\x0f", 16 => "\x10", 17 => "\x11", 18 => "\x12", 19 => "\x13", 20 => "\x14", 21 => "\x15", 22 => "\x16", 23 => "\x17", 24 => "\x18\x18", 25 => "\x18\x19", 26 => "\x18\x1a", 32 => "\x18 ", 50 => "\x182", 64 => "\x18@", 100 => "\x18d", 128 => "\x18\x80", 200 => "\x18\xc8", 255 => "\x18\xff", 256 => "\x19\x01\x00", 500 => "\x19\x01\xf4", 1000 => "\x19\x03\xe8", 1023 => "\x19\x03\xff"];
    /**
     * Pre-encoded negative integers -1 to -24 and common larger values
     * CBOR major type 1 (negative integer)
     */
    private const NEG_CACHE = [-1 => " ", -2 => "!", -3 => "\"", -4 => "#", -5 => "\$", -10 => ")", -20 => "3", -24 => "7", -25 => "8\x18", -50 => "81", -100 => "8c"];
    /**
     * Encode a PHP value to CBOR binary string
     *
     * @param mixed $value The value to encode
     *
     * @return string
     */
    public function encode(mixed $value) : string
    {
        return $this->encodeValue($value);
    }
    /**
     * Recursively encode a value to CBOR
     *
     * @param mixed $value Value to encode
     * @return string Encoded CBOR bytes
     */
    private function encodeValue(mixed $value) : string
    {
        switch (\gettype($value)) {
            case 'string':
                $len = \strlen($value);
                if ($len < 24) {
                    return \chr(0x60 | $len) . $value;
                }
                if ($len < 0x100) {
                    return "x" . \chr($len) . $value;
                }
                return $this->encodeTextString($value);
            case 'array':
                if (isset($value['__cbor_timestamp'])) {
                    return "\xc1\xfb" . \pack('E', $value['__cbor_timestamp']);
                }
                // Encode a byte string (major type 2)
                if (isset($value['__cbor_bytes'])) {
                    $bytes = $value['__cbor_bytes'];
                    $len = \strlen($bytes);
                    if ($len < 24) {
                        return \chr(0x40 | $len) . $bytes;
                    }
                    if ($len < 0x100) {
                        return "X" . \chr($len) . $bytes;
                    }
                    if ($len < 0x10000) {
                        return "Y" . \pack('n', $len) . $bytes;
                    }
                    return "Z" . \pack('N', $len) . $bytes;
                }
                if (\array_is_list($value)) {
                    return $this->encodeArray($value);
                }
                return $this->encodeMap($value);
            case 'integer':
                if (isset(self::INT_CACHE[$value])) {
                    return self::INT_CACHE[$value];
                }
                if (isset(self::NEG_CACHE[$value])) {
                    return self::NEG_CACHE[$value];
                }
                // Fast path for positive integers
                // Major type 0: unsigned integer
                if ($value >= 0) {
                    if ($value < 24) {
                        return \chr($value);
                    }
                    if ($value < 0x100) {
                        return "\x18" . \chr($value);
                    }
                    if ($value < 0x10000) {
                        return "\x19" . \pack('n', $value);
                    }
                    if ($value < 0x100000000) {
                        return "\x1a" . \pack('N', $value);
                    }
                    return "\x1b" . \pack('J', $value);
                }
                return $this->encodeInteger($value);
            case 'double':
                // Encode a float (major type 7, float 64)
                return "\xfb" . \pack('E', $value);
            case 'boolean':
                // Encode a boolean (major type 7, simple)
                return $value ? "\xf5" : "\xf4";
            case 'NULL':
                // Encode null (major type 7, simple)
                return "\xf6";
            case 'object':
                throw new CborException("Cannot encode object of type: " . \get_class($value));
            default:
                throw new CborException("Cannot encode value of type: " . \gettype($value));
        }
    }
    /**
     * Encode an integer (major type 0 or 1)
     *
     * @param int $value
     * @return string
     */
    private function encodeInteger(int $value) : string
    {
        if (isset(self::INT_CACHE[$value])) {
            return self::INT_CACHE[$value];
        }
        if (isset(self::NEG_CACHE[$value])) {
            return self::NEG_CACHE[$value];
        }
        if ($value >= 0) {
            // Major type 0: unsigned integer
            if ($value < 24) {
                return \chr($value);
            }
            if ($value < 0x100) {
                return "\x18" . \chr($value);
            }
            if ($value < 0x10000) {
                return "\x19" . \pack('n', $value);
            }
            if ($value < 0x100000000) {
                return "\x1a" . \pack('N', $value);
            }
            return "\x1b" . \pack('J', $value);
        }
        // Major type 1: negative integer (-1 - n)
        $value = -1 - $value;
        if ($value < 24) {
            return \chr(0x20 | $value);
        }
        if ($value < 0x100) {
            return "8" . \chr($value);
        }
        if ($value < 0x10000) {
            return "9" . \pack('n', $value);
        }
        if ($value < 0x100000000) {
            return ":" . \pack('N', $value);
        }
        return ";" . \pack('J', $value);
    }
    /**
     * Encode a text string (major type 3)
     *
     * @param string $value
     * @return string
     */
    private function encodeTextString(string $value) : string
    {
        $len = \strlen($value);
        if ($len < 24) {
            return \chr(0x60 | $len) . $value;
        }
        if ($len < 0x100) {
            return "x" . \chr($len) . $value;
        }
        if ($len < 0x10000) {
            return "y" . \pack('n', $len) . $value;
        }
        if ($len < 0x100000000) {
            return "z" . \pack('N', $len) . $value;
        }
        return "{" . \pack('J', $len) . $value;
    }
    /**
     * Encode an array (major type 4)
     *
     * @param array $value
     * @return string
     */
    private function encodeArray(array $value) : string
    {
        $count = \count($value);
        if ($count < 24) {
            $result = \chr(0x80 | $count);
        } elseif ($count < 0x100) {
            $result = "\x98" . \chr($count);
        } elseif ($count < 0x10000) {
            $result = "\x99" . \pack('n', $count);
        } elseif ($count < 0x100000000) {
            $result = "\x9a" . \pack('N', $count);
        } else {
            $result = "\x9b" . \pack('J', $count);
        }
        foreach ($value as $item) {
            $result .= $this->encodeValue($item);
        }
        return $result;
    }
    /**
     * Encode a map (major type 5)
     *
     * @param array $value
     * @return string
     */
    private function encodeMap(array $value) : string
    {
        $count = \count($value);
        if ($count < 24) {
            $result = \chr(0xa0 | $count);
        } elseif ($count < 0x100) {
            $result = "\xb8" . \chr($count);
        } elseif ($count < 0x10000) {
            $result = "\xb9" . \pack('n', $count);
        } elseif ($count < 0x100000000) {
            $result = "\xba" . \pack('N', $count);
        } else {
            $result = "\xbb" . \pack('J', $count);
        }
        foreach ($value as $k => $v) {
            if (\is_int($k)) {
                $result .= $this->encodeInteger($k);
            } else {
                $len = \strlen($k);
                if ($len < 24) {
                    $result .= \chr(0x60 | $len) . $k;
                } elseif ($len < 0x100) {
                    $result .= "x" . \chr($len) . $k;
                } else {
                    $result .= "y" . \pack('n', $len) . $k;
                }
            }
            $result .= $this->encodeValue($v);
        }
        return $result;
    }
    /**
     * Create an empty map (major type 5 with 0 elements)
     *
     * @return string
     */
    public function encodeEmptyMap() : string
    {
        return "\xa0";
    }
    /**
     * Create an empty indefinite map (major type 5 indefinite length)
     *
     * @return string
     */
    public function encodeEmptyIndefiniteMap() : string
    {
        return "\xbf\xff";
    }
}
