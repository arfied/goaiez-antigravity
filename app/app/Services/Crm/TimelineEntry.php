<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Enums\TimelineEntryType;
use Carbon\CarbonImmutable;

/**
 * One line of a contact's history, whichever table it came from.
 *
 * `34` §1.2: *"each = icon + one sentence + relative time"*. The sentence is
 * built where the source is read, not here — a value object that knew how to
 * phrase a review and a consent grant would be the fifth place this application
 * decides what a routing decision is called.
 *
 * ⚠️ **`occurredAt` IS NULLABLE AND THAT IS NOT A CONVENIENCE.** Three of the
 * four sources have a nullable timestamp — Laravel's own `timestamps()` default
 * leaves 40 of 43 tables that way — and an entry with no time is honest about a
 * row nobody dated. What it must never do is sort as though it were the newest
 * thing that happened, which is exactly what a DESC ordering in Postgres would
 * do with it; `CustomerTimeline` sorts these in PHP with nulls last for that
 * reason.
 */
final readonly class TimelineEntry
{
    public function __construct(
        public TimelineEntryType $type,
        public ?CarbonImmutable $occurredAt,
        /** One sentence, in the owner's words. */
        public string $headline,
        /** What they actually said or wrote, when there is any. */
        public ?string $detail = null,
        /** Who did it, when a person did. */
        public ?string $actor = null,
    ) {}
}
