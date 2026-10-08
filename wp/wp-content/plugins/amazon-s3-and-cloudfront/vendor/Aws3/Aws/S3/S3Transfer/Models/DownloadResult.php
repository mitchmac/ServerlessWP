<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\S3\S3Transfer\Models;

use DeliciousBrains\WP_Offload_Media\Aws3\Aws\Result;
final class DownloadResult extends Result
{
    private readonly mixed $downloadDataResult;
    /**
     * @param mixed $downloadDataResult
     * @param array $data
     */
    public function __construct(mixed $downloadDataResult, array $data = [])
    {
        parent::__construct($data);
        $this->downloadDataResult = $downloadDataResult;
    }
    /**
     * @return mixed
     */
    public function getDownloadDataResult() : mixed
    {
        return $this->downloadDataResult;
    }
}
