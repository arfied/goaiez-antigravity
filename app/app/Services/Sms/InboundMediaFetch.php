<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Enums\InboundMediaOutcome;

/**
 * What {@see InboundMediaFetcher} came back with.
 *
 * ⚠️ **THE BYTES AND THE OUTCOME TRAVEL TOGETHER SO THAT NEITHER CAN BE READ
 * WITHOUT THE OTHER.** A fetcher returning `?string $bytes` would make `null`
 * mean five different refusals, and the caller would have to reconstruct which —
 * which is how a "too large" ends up recorded as "unreachable" and an operator
 * spends an afternoon on a CDN that was working perfectly.
 *
 * ⛔ **IT CARRIES NO URL**, deliberately, so that nothing downstream can persist
 * or re-fetch the address. See the creating migration for the table's half of
 * the same rule.
 */
final readonly class InboundMediaFetch
{
    private function __construct(
        public InboundMediaOutcome $outcome,
        public ?string $bytes = null,
        public ?string $contentType = null,
    ) {}

    public static function stored(string $bytes, string $contentType): self
    {
        return new self(InboundMediaOutcome::Stored, $bytes, $contentType);
    }

    /**
     * ⚠️ **`Stored` IS UNSPELLABLE HERE, AND THE ASSERTION IS WHY THIS IS NOT A
     * PUBLIC CONSTRUCTOR.** A refusal carrying bytes is the one shape the
     * migration's CHECK would reject at the very end of the path, in a job, as
     * SQLSTATE 23514 — a long way from whoever wrote it.
     */
    public static function refused(InboundMediaOutcome $outcome): self
    {
        assert(! $outcome->isStored(), 'A refusal cannot be a stored outcome.');

        return new self($outcome);
    }
}
