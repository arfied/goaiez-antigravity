<?php

declare(strict_types=1);

namespace App\Services\Voice\Live;

use Carbon\CarbonImmutable;

/**
 * What a call token carries once opened: the business and the call it was minted for. The tenant of every later voice
 * brain request comes from here and from nothing the worker sends (AI receptionist plan, 2026-10-05).
 */
final readonly class LiveCallClaim
{
    public function __construct(
        public int $businessId,
        public int $callId,
        public CarbonImmutable $issuedAt,
    ) {}
}
