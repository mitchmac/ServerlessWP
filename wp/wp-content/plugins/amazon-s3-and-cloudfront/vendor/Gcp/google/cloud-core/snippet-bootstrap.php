<?php

namespace DeliciousBrains\WP_Offload_Media\Gcp;

use DeliciousBrains\WP_Offload_Media\Gcp\DG\BypassFinals;
use DeliciousBrains\WP_Offload_Media\Gcp\Google\Cloud\Core\Testing\TestHelpers;
TestHelpers::snippetBootstrap();
\date_default_timezone_set('UTC');
// Make sure that while testing we bypass the `final` keyword for the GAPIC client.
// Only run this if the individual component has the helper package installed
if (\class_exists(BypassFinals::class)) {
    BypassFinals::enable();
}
