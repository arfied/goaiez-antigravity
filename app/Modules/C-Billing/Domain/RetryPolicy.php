<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Domain;

final class RetryPolicy
{
    private const MAX_ATTEMPTS = 5;

    /**
     * [G1-33] exponential backoff with a hard attempt ceiling
     * [G1-42], [G1-49], [G1-59], [G4-39]
     */
    public function getNextRetryDelaySeconds(int $attempt): int
    {
        if ($attempt > self::MAX_ATTEMPTS) {
            throw new \RuntimeException('Hard attempt ceiling reached');
        }

        // Exponential backoff: 2^attempt
        return 2 ** $attempt;
    }
}
