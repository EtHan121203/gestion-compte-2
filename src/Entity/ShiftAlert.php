<?php

namespace App\Entity;

use App\Entity\ShiftBucket;

/**
 * Shift Alert
 *
 */
class ShiftAlert
{

    private ShiftBucket $bucket;
    private string $issue;

    public function __construct(ShiftBucket $bucket, string $issue)
    {
        $this->bucket = $bucket;
        $this->issue = $issue;
    }

    /**
     * @return ShiftBucket
     */
    public function getBucket(): ShiftBucket
    {
        return $this->bucket;
    }

    /**
     * @return string
     */
    public function getIssue(): string
    {
        return $this->issue;
    }

}
