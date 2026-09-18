<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\S3\S3Transfer\Progress;

interface ProgressTrackerInterface
{
    /**
     * To show the progress being tracked.
     *
     * @return void
     */
    public function showProgress() : void;
}
