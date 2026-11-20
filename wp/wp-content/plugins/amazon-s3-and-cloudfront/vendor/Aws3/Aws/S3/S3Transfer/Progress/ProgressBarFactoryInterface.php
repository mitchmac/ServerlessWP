<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\S3\S3Transfer\Progress;

interface ProgressBarFactoryInterface
{
    public function __invoke() : ProgressBarInterface;
}
