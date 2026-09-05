<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Events;

/**
 * The review ask is a Marketing-class send (R231 — send.requested's shape, messageClass first-class). (R245) 2026-09-05.
 */
final class ReviewRequested
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $reviewRequestId,
        public readonly string $platform,
        public readonly string $messageClass
    ) {}
}
