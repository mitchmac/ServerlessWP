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
namespace DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Builder;

use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Collection\AbstractCollection;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Converter\Number\GenericNumberConverter;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Converter\Time\GenericTimeConverter;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Converter\Time\PhpTimeConverter;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Guid\GuidBuilder;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Math\BrickMathCalculator;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Nonstandard\UuidBuilder as NonstandardUuidBuilder;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Rfc4122\UuidBuilder as Rfc4122UuidBuilder;
use Traversable;
/**
 * A collection of UuidBuilderInterface objects
 *
 * @extends AbstractCollection<UuidBuilderInterface>
 */
class BuilderCollection extends AbstractCollection
{
    public function getType() : string
    {
        return UuidBuilderInterface::class;
    }
    /**
     * @psalm-mutation-free
     * @psalm-suppress ImpureMethodCall
     * @psalm-suppress InvalidTemplateParam
     */
    public function getIterator() : Traversable
    {
        return parent::getIterator();
    }
    /**
     * Re-constructs the object from its serialized form
     *
     * @param string $serialized The serialized PHP string to unserialize into
     *     a UuidInterface instance
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     * @psalm-suppress RedundantConditionGivenDocblockType
     */
    public function unserialize($serialized) : void
    {
        /** @var array<array-key, UuidBuilderInterface> $data */
        $data = \unserialize($serialized, ['allowed_classes' => [BrickMathCalculator::class, GenericNumberConverter::class, GenericTimeConverter::class, GuidBuilder::class, NonstandardUuidBuilder::class, PhpTimeConverter::class, Rfc4122UuidBuilder::class]]);
        $this->data = \array_filter($data, function ($unserialized) : bool {
            return $unserialized instanceof UuidBuilderInterface;
        });
    }
}
