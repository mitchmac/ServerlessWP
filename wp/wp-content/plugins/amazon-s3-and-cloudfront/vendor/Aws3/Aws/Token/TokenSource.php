<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\Token;

enum TokenSource : string
{
    case BEARER_SERVICE_ENV_VARS = 'bearer_service_env_vars';
}
