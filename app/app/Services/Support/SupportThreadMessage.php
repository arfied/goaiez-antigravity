<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Enums\SupportChannel;
use App\Enums\SupportMessageAuthor;
use Carbon\CarbonImmutable;

/**
 * One message inside a {@see SupportThread}.
 *
 * Carries the side that spoke and not the person's name: the thread is rendered
 * to the tenant, and *which agent* answered is our internal detail — `28` §9.4's
 * owner notification names the platform, not the individual, and this keeps the
 * two screens from disagreeing about that.
 */
final readonly class SupportThreadMessage
{
    public function __construct(
        public int $id,
        public SupportMessageAuthor $author,
        public SupportChannel $channel,
        public string $body,
        public CarbonImmutable $writtenAt,
    ) {}
}
