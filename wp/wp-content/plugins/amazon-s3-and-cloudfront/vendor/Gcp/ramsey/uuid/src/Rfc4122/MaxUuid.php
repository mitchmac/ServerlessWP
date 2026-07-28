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

use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Uuid;
/**
 * The max UUID is a special form of UUID that has all 128 bits set to one (`1`)
 *
 * @link https://www.rfc-editor.org/rfc/rfc9562#section-5.10 RFC 9562, 5.10. Max UUID
 *
 * @immutable
 */
final class MaxUuid extends Uuid implements UuidInterface
{
}
