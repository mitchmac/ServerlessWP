<?php

namespace DeliciousBrains\WP_Offload_Media\Gcp;

use DeliciousBrains\WP_Offload_Media\Gcp\DG\BypassFinals;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\ApiCore\Testing\MessageAwareArrayComparator;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\ApiCore\Testing\ProtobufGPBEmptyComparator;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\ApiCore\Testing\ProtobufMessageComparator;
\date_default_timezone_set('UTC');
\DeliciousBrains\WP_Offload_Media\Gcp\SebastianBergmann\Comparator\Factory::getInstance()->register(new MessageAwareArrayComparator());
\DeliciousBrains\WP_Offload_Media\Gcp\SebastianBergmann\Comparator\Factory::getInstance()->register(new ProtobufMessageComparator());
\DeliciousBrains\WP_Offload_Media\Gcp\SebastianBergmann\Comparator\Factory::getInstance()->register(new ProtobufGPBEmptyComparator());
// Make sure that while testing we bypass the `final` keyword for the GAPIC client.
// Only run this if the individual component has the helper package installed
if (\class_exists(BypassFinals::class)) {
    BypassFinals::enable();
}
