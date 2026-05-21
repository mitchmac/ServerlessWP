<?php

namespace DeliciousBrains\WP_Offload_Media\Gcp\Psr\Log;

/**
 * Basic Implementation of LoggerAwareInterface.
 */
trait LoggerAwareTrait
{
    /**
     * The logger instance.
     */
    protected ?LoggerInterface $logger = null;
    /**
     * Sets a logger.
     */
    public function setLogger(LoggerInterface $logger) : void
    {
        $this->logger = $logger;
    }
}
