<?php

declare (strict_types=1);
/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace DeliciousBrains\WP_Offload_Media\Gcp\Monolog\Handler;

use DeliciousBrains\WP_Offload_Media\Gcp\Monolog\Formatter\FormatterInterface;
use DeliciousBrains\WP_Offload_Media\Gcp\Monolog\Formatter\NormalizerFormatter;
use DeliciousBrains\WP_Offload_Media\Gcp\Monolog\Level;
use DeliciousBrains\WP_Offload_Media\Gcp\Monolog\LogRecord;
/**
 * Handler sending logs to Zend Monitor
 *
 * @author  Christian Bergau <cbergau86@gmail.com>
 * @author  Jason Davis <happydude@jasondavis.net>
 */
class ZendMonitorHandler extends AbstractProcessingHandler
{
    /**
     * @throws MissingExtensionException
     */
    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = \true)
    {
        if (!\function_exists('DeliciousBrains\\WP_Offload_Media\\Gcp\\zend_monitor_custom_event')) {
            throw new MissingExtensionException('You must have Zend Server installed with Zend Monitor enabled in order to use this handler');
        }
        parent::__construct($level, $bubble);
    }
    /**
     * Translates Monolog log levels to ZendMonitor levels.
     */
    protected function toZendMonitorLevel(Level $level) : int
    {
        return match ($level) {
            Level::Debug => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_INFO,
            Level::Info => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_INFO,
            Level::Notice => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_INFO,
            Level::Warning => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_WARNING,
            Level::Error => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_ERROR,
            Level::Critical => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_ERROR,
            Level::Alert => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_ERROR,
            Level::Emergency => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_ERROR,
        };
    }
    /**
     * @inheritDoc
     */
    protected function write(LogRecord $record) : void
    {
        $this->writeZendMonitorCustomEvent($record->level->getName(), $record->message, $record->formatted, $this->toZendMonitorLevel($record->level));
    }
    /**
     * Write to Zend Monitor Events
     * @param string       $type      Text displayed in "Class Name (custom)" field
     * @param string       $message   Text displayed in "Error String"
     * @param array<mixed> $formatted Displayed in Custom Variables tab
     * @param int          $severity  Set the event severity level (-1,0,1)
     */
    protected function writeZendMonitorCustomEvent(string $type, string $message, array $formatted, int $severity) : void
    {
        zend_monitor_custom_event($type, $message, $formatted, $severity);
    }
    /**
     * @inheritDoc
     */
    public function getDefaultFormatter() : FormatterInterface
    {
        return new NormalizerFormatter();
    }
}
