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
namespace DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid;

use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Builder\FallbackBuilder;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Builder\UuidBuilderInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Codec\CodecInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Codec\GuidStringCodec;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Codec\StringCodec;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Converter\Number\GenericNumberConverter;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Converter\NumberConverterInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Converter\Time\GenericTimeConverter;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Converter\Time\PhpTimeConverter;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Converter\TimeConverterInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Generator\DceSecurityGenerator;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Generator\DceSecurityGeneratorInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Generator\NameGeneratorFactory;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Generator\NameGeneratorInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Generator\PeclUuidNameGenerator;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Generator\PeclUuidRandomGenerator;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Generator\PeclUuidTimeGenerator;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Generator\RandomGeneratorFactory;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Generator\RandomGeneratorInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Generator\TimeGeneratorFactory;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Generator\TimeGeneratorInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Generator\UnixTimeGenerator;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Guid\GuidBuilder;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Math\BrickMathCalculator;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Math\CalculatorInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Nonstandard\UuidBuilder as NonstandardUuidBuilder;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Provider\Dce\SystemDceSecurityProvider;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Provider\DceSecurityProviderInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Provider\Node\FallbackNodeProvider;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Provider\Node\RandomNodeProvider;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Provider\Node\SystemNodeProvider;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Provider\NodeProviderInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Provider\Time\SystemTimeProvider;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Provider\TimeProviderInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Rfc4122\UuidBuilder as Rfc4122UuidBuilder;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Validator\GenericValidator;
use DeliciousBrains\WP_Offload_Media\Gcp\Ramsey\Uuid\Validator\ValidatorInterface;
use const PHP_INT_SIZE;
/**
 * FeatureSet detects and exposes available features in the current environment
 *
 * A feature set is used by UuidFactory to determine the available features and capabilities of the environment.
 */
class FeatureSet
{
    private ?TimeProviderInterface $timeProvider = null;
    private CalculatorInterface $calculator;
    private CodecInterface $codec;
    private DceSecurityGeneratorInterface $dceSecurityGenerator;
    private NameGeneratorInterface $nameGenerator;
    private NodeProviderInterface $nodeProvider;
    private NumberConverterInterface $numberConverter;
    private RandomGeneratorInterface $randomGenerator;
    private TimeConverterInterface $timeConverter;
    private TimeGeneratorInterface $timeGenerator;
    private TimeGeneratorInterface $unixTimeGenerator;
    private UuidBuilderInterface $builder;
    private ValidatorInterface $validator;
    /**
     * @param bool $useGuids True build UUIDs using the GuidStringCodec
     * @param bool $force32Bit True to force the use of 32-bit functionality (primarily for testing purposes)
     * @param bool $forceNoBigNumber (obsolete)
     * @param bool $ignoreSystemNode True to disable attempts to check for the system node ID (primarily for testing purposes)
     * @param bool $enablePecl True to enable the use of the PeclUuidTimeGenerator to generate version 1 UUIDs
     *
     * @phpstan-ignore constructor.unusedParameter ($forceNoBigNumber is deprecated)
     */
    public function __construct(bool $useGuids = \false, private bool $force32Bit = \false, bool $forceNoBigNumber = \false, private bool $ignoreSystemNode = \false, private bool $enablePecl = \false)
    {
        $this->randomGenerator = $this->buildRandomGenerator();
        $this->setCalculator(new BrickMathCalculator());
        $this->builder = $this->buildUuidBuilder($useGuids);
        $this->codec = $this->buildCodec($useGuids);
        $this->nodeProvider = $this->buildNodeProvider();
        $this->nameGenerator = $this->buildNameGenerator();
        $this->setTimeProvider(new SystemTimeProvider());
        $this->setDceSecurityProvider(new SystemDceSecurityProvider());
        $this->validator = new GenericValidator();
        \assert($this->timeProvider !== null);
        $this->unixTimeGenerator = $this->buildUnixTimeGenerator();
    }
    /**
     * Returns the builder configured for this environment
     */
    public function getBuilder() : UuidBuilderInterface
    {
        return $this->builder;
    }
    /**
     * Returns the calculator configured for this environment
     */
    public function getCalculator() : CalculatorInterface
    {
        return $this->calculator;
    }
    /**
     * Returns the codec configured for this environment
     */
    public function getCodec() : CodecInterface
    {
        return $this->codec;
    }
    /**
     * Returns the DCE Security generator configured for this environment
     */
    public function getDceSecurityGenerator() : DceSecurityGeneratorInterface
    {
        return $this->dceSecurityGenerator;
    }
    /**
     * Returns the name generator configured for this environment
     */
    public function getNameGenerator() : NameGeneratorInterface
    {
        return $this->nameGenerator;
    }
    /**
     * Returns the node provider configured for this environment
     */
    public function getNodeProvider() : NodeProviderInterface
    {
        return $this->nodeProvider;
    }
    /**
     * Returns the number converter configured for this environment
     */
    public function getNumberConverter() : NumberConverterInterface
    {
        return $this->numberConverter;
    }
    /**
     * Returns the random generator configured for this environment
     */
    public function getRandomGenerator() : RandomGeneratorInterface
    {
        return $this->randomGenerator;
    }
    /**
     * Returns the time converter configured for this environment
     */
    public function getTimeConverter() : TimeConverterInterface
    {
        return $this->timeConverter;
    }
    /**
     * Returns the time generator configured for this environment
     */
    public function getTimeGenerator() : TimeGeneratorInterface
    {
        return $this->timeGenerator;
    }
    /**
     * Returns the Unix Epoch time generator configured for this environment
     */
    public function getUnixTimeGenerator() : TimeGeneratorInterface
    {
        return $this->unixTimeGenerator;
    }
    /**
     * Returns the validator configured for this environment
     */
    public function getValidator() : ValidatorInterface
    {
        return $this->validator;
    }
    /**
     * Sets the calculator to use in this environment
     */
    public function setCalculator(CalculatorInterface $calculator) : void
    {
        $this->calculator = $calculator;
        $this->numberConverter = $this->buildNumberConverter($calculator);
        $this->timeConverter = $this->buildTimeConverter($calculator);
        if (isset($this->timeProvider)) {
            $this->timeGenerator = $this->buildTimeGenerator($this->timeProvider);
        }
    }
    /**
     * Sets the DCE Security provider to use in this environment
     */
    public function setDceSecurityProvider(DceSecurityProviderInterface $dceSecurityProvider) : void
    {
        $this->dceSecurityGenerator = $this->buildDceSecurityGenerator($dceSecurityProvider);
    }
    /**
     * Sets the node provider to use in this environment
     */
    public function setNodeProvider(NodeProviderInterface $nodeProvider) : void
    {
        $this->nodeProvider = $nodeProvider;
        if (isset($this->timeProvider)) {
            $this->timeGenerator = $this->buildTimeGenerator($this->timeProvider);
        }
    }
    /**
     * Sets the time provider to use in this environment
     */
    public function setTimeProvider(TimeProviderInterface $timeProvider) : void
    {
        $this->timeProvider = $timeProvider;
        $this->timeGenerator = $this->buildTimeGenerator($timeProvider);
    }
    /**
     * Set the validator to use in this environment
     */
    public function setValidator(ValidatorInterface $validator) : void
    {
        $this->validator = $validator;
    }
    /**
     * Returns a codec configured for this environment
     *
     * @param bool $useGuids Whether to build UUIDs using the GuidStringCodec
     */
    private function buildCodec(bool $useGuids = \false) : CodecInterface
    {
        if ($useGuids) {
            return new GuidStringCodec($this->builder);
        }
        return new StringCodec($this->builder);
    }
    /**
     * Returns a DCE Security generator configured for this environment
     */
    private function buildDceSecurityGenerator(DceSecurityProviderInterface $dceSecurityProvider) : DceSecurityGeneratorInterface
    {
        return new DceSecurityGenerator($this->numberConverter, $this->timeGenerator, $dceSecurityProvider);
    }
    /**
     * Returns a node provider configured for this environment
     */
    private function buildNodeProvider() : NodeProviderInterface
    {
        if ($this->ignoreSystemNode) {
            return new RandomNodeProvider();
        }
        return new FallbackNodeProvider([new SystemNodeProvider(), new RandomNodeProvider()]);
    }
    /**
     * Returns a number converter configured for this environment
     */
    private function buildNumberConverter(CalculatorInterface $calculator) : NumberConverterInterface
    {
        return new GenericNumberConverter($calculator);
    }
    /**
     * Returns a random generator configured for this environment
     */
    private function buildRandomGenerator() : RandomGeneratorInterface
    {
        if ($this->enablePecl) {
            return new PeclUuidRandomGenerator();
        }
        return (new RandomGeneratorFactory())->getGenerator();
    }
    /**
     * Returns a time generator configured for this environment
     *
     * @param TimeProviderInterface $timeProvider The time provider to use with
     *     the time generator
     */
    private function buildTimeGenerator(TimeProviderInterface $timeProvider) : TimeGeneratorInterface
    {
        if ($this->enablePecl) {
            return new PeclUuidTimeGenerator();
        }
        return (new TimeGeneratorFactory($this->nodeProvider, $this->timeConverter, $timeProvider))->getGenerator();
    }
    /**
     * Returns a Unix Epoch time generator configured for this environment
     */
    private function buildUnixTimeGenerator() : TimeGeneratorInterface
    {
        return new UnixTimeGenerator($this->randomGenerator);
    }
    /**
     * Returns a name generator configured for this environment
     */
    private function buildNameGenerator() : NameGeneratorInterface
    {
        if ($this->enablePecl) {
            return new PeclUuidNameGenerator();
        }
        return (new NameGeneratorFactory())->getGenerator();
    }
    /**
     * Returns a time converter configured for this environment
     */
    private function buildTimeConverter(CalculatorInterface $calculator) : TimeConverterInterface
    {
        $genericConverter = new GenericTimeConverter($calculator);
        if ($this->is64BitSystem()) {
            return new PhpTimeConverter($calculator, $genericConverter);
        }
        return $genericConverter;
    }
    /**
     * Returns a UUID builder configured for this environment
     *
     * @param bool $useGuids Whether to build UUIDs using the GuidStringCodec
     */
    private function buildUuidBuilder(bool $useGuids = \false) : UuidBuilderInterface
    {
        if ($useGuids) {
            return new GuidBuilder($this->numberConverter, $this->timeConverter);
        }
        return new FallbackBuilder([new Rfc4122UuidBuilder($this->numberConverter, $this->timeConverter), new NonstandardUuidBuilder($this->numberConverter, $this->timeConverter)]);
    }
    /**
     * Returns true if the PHP build is 64-bit
     */
    private function is64BitSystem() : bool
    {
        return PHP_INT_SIZE === 8 && !$this->force32Bit;
    }
}
