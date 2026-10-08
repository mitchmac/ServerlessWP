<?php

declare (strict_types=1);
namespace DeliciousBrains\WP_Offload_Media\Gcp\GuzzleHttp;

use DeliciousBrains\WP_Offload_Media\Gcp\Psr\Http\Message\MessageInterface;
final class BodySummarizer implements BodySummarizerInterface
{
    private ?int $truncateAt;
    public function __construct(?int $truncateAt = null)
    {
        $this->truncateAt = $truncateAt;
    }
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message) : ?string
    {
        try {
            return Psr7\Message::bodySummary($message, $this->truncateAt);
        } catch (\Exception $e) {
            return null;
        }
    }
}
