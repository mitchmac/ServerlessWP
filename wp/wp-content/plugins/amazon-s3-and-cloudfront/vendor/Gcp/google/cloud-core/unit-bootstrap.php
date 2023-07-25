<?php

namespace DeliciousBrains\WP_Offload_Media\Gcp;

use DeliciousBrains\WP_Offload_Media\Gcp\Google\ApiCore\Testing\MessageAwareArrayComparator;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\ApiCore\Testing\ProtobufMessageComparator;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\ApiCore\Testing\ProtobufGPBEmptyComparator;
\date_default_timezone_set('UTC');
\DeliciousBrains\WP_Offload_Media\Gcp\SebastianBergmann\Comparator\Factory::getInstance()->register(new MessageAwareArrayComparator());
\DeliciousBrains\WP_Offload_Media\Gcp\SebastianBergmann\Comparator\Factory::getInstance()->register(new ProtobufMessageComparator());
\DeliciousBrains\WP_Offload_Media\Gcp\SebastianBergmann\Comparator\Factory::getInstance()->register(new ProtobufGPBEmptyComparator());
