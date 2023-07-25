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
use DeliciousBrains\WP_Offload_Media\Gcp\Monolog\Logger;
/**
 * Handler sending logs to Zend Monitor
 *
 * @author  Christian Bergau <cbergau86@gmail.com>
 * @author  Jason Davis <happydude@jasondavis.net>
 *
 * @phpstan-import-type FormattedRecord from AbstractProcessingHandler
 */
class ZendMonitorHandler extends AbstractProcessingHandler
{
    /**
     * Monolog level / ZendMonitor Custom Event priority map
     *
     * @var array<int, int>
     */
    protected $levelMap = [];
    /**
     * @throws MissingExtensionException
     */
    public function __construct($level = Logger::DEBUG, bool $bubble = \true)
    {
        if (!\function_exists('DeliciousBrains\\WP_Offload_Media\\Gcp\\zend_monitor_custom_event')) {
            throw new MissingExtensionException('You must have Zend Server installed with Zend Monitor enabled in order to use this handler');
        }
        //zend monitor constants are not defined if zend monitor is not enabled.
        $this->levelMap = [Logger::DEBUG => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_INFO, Logger::INFO => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_INFO, Logger::NOTICE => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_INFO, Logger::WARNING => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_WARNING, Logger::ERROR => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_ERROR, Logger::CRITICAL => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_ERROR, Logger::ALERT => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_ERROR, Logger::EMERGENCY => \DeliciousBrains\WP_Offload_Media\Gcp\ZEND_MONITOR_EVENT_SEVERITY_ERROR];
        parent::__construct($level, $bubble);
    }
    /**
     * {@inheritDoc}
     */
    protected function write(array $record) : void
    {
        $this->writeZendMonitorCustomEvent(Logger::getLevelName($record['level']), $record['message'], $record['formatted'], $this->levelMap[$record['level']]);
    }
    /**
     * Write to Zend Monitor Events
     * @param string $type      Text displayed in "Class Name (custom)" field
     * @param string $message   Text displayed in "Error String"
     * @param array  $formatted Displayed in Custom Variables tab
     * @param int    $severity  Set the event severity level (-1,0,1)
     *
     * @phpstan-param FormattedRecord $formatted
     */
    protected function writeZendMonitorCustomEvent(string $type, string $message, array $formatted, int $severity) : void
    {
        zend_monitor_custom_event($type, $message, $formatted, $severity);
    }
    /**
     * {@inheritDoc}
     */
    public function getDefaultFormatter() : FormatterInterface
    {
        return new NormalizerFormatter();
    }
    /**
     * @return array<int, int>
     */
    public function getLevelMap() : array
    {
        return $this->levelMap;
    }
}
