<?php

namespace DeliciousBrains\WP_Offload_Media\Aws3\Aws\S3\S3Transfer;

use DeliciousBrains\WP_Offload_Media\Aws3\Aws\CommandInterface;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\Result;
use DeliciousBrains\WP_Offload_Media\Aws3\Aws\ResultInterface;
/**
 * Multipart downloader using the part get approach.
 */
final class PartGetMultipartDownloader extends AbstractMultipartDownloader
{
    /**
     * @inheritDoc
     */
    protected function getFetchCommandArgs() : array
    {
        $nextCommandArgs = $this->downloadRequestArgs;
        $nextCommandArgs['PartNumber'] = $this->currentPartNo;
        return $nextCommandArgs;
    }
    /**
     * @inheritDoc
     *
     * @param Result $result
     *
     * @return void
     */
    protected function computeObjectDimensions(ResultInterface $result) : void
    {
        if (!empty($result['PartsCount'])) {
            $this->objectPartsCount = $result['PartsCount'];
        } else {
            $this->objectPartsCount = 1;
        }
        $this->objectSizeInBytes = self::computeObjectSizeFromContentRange($result['ContentRange'] ?? "");
    }
}
