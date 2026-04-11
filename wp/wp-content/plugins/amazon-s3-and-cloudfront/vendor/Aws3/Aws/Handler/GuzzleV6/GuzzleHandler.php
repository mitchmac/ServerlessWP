<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\Handler\GuzzleV6;

\trigger_error(\sprintf('Using the "%s" class is deprecated, use "%s" instead.', __NAMESPACE__ . '\\GuzzleHandler', \DeliciousBrains\WP_Offload_Media\Aws3\Aws\Handler\Guzzle\GuzzleHandler::class), \E_USER_DEPRECATED);
\class_alias(\DeliciousBrains\WP_Offload_Media\Aws3\Aws\Handler\Guzzle\GuzzleHandler::class, __NAMESPACE__ . '\\GuzzleHandler');
