<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Contracts\CmsAdapter;
use App\Enums\ActuationTier;
use App\Enums\PublishRefusal;

/**
 * What one attempt at putting a page on a tenant's website did.
 *
 * ⚠️ **A REFUSAL IS A RETURN VALUE**, on {@see CmsAdapter}'s
 * rule. Every one of {@see PublishRefusal}'s cases is an ordinary condition —
 * a switch that is off, a window that is open, a cap that is spent — and none of
 * them is a reason to fail a queued job and burn a retry.
 */
final readonly class PublishOutcome
{
    /**
     * @param  ?PageAdvisory  $advisory  The paste-ready T4 hand-off, when that
     *                                   is what happened.
     */
    private function __construct(
        public bool $published,
        public ?PublishRefusal $refusal,
        public ?ActuationTier $tier = null,
        public ?string $url = null,
        public ?int $siteChangeId = null,
        public ?PageAdvisory $advisory = null,
    ) {}

    public static function published(ActuationTier $tier, string $url, int $siteChangeId): self
    {
        return new self(true, null, $tier, $url, $siteChangeId);
    }

    /**
     * The advisory tier did its whole job: the owner has the copy to paste.
     *
     * ⛔ **`published` IS FALSE AND THE ADVISORY IS NOT A REFUSAL EITHER**
     * (`41` Part 1, and `ActuationTier::T4`'s own docblock: *"a real tier and
     * not a failure"*). Nothing reached the website, so saying otherwise would
     * be the feed telling an owner we changed a page we did not touch — and
     * `refusal` is null because nobody was turned down.
     */
    public static function handedOff(PageAdvisory $advisory): self
    {
        return new self(false, null, ActuationTier::T4, $advisory->url, null, $advisory);
    }

    public static function refused(PublishRefusal $refusal): self
    {
        return new self(false, $refusal);
    }

    /**
     * What the run row records.
     *
     * ⚠️ **NO PAGE COPY.** `automation_runs.output` is read on staff screens and
     * kept indefinitely; the copy is on the `growth_pages` row this names.
     *
     * @return array<string, mixed>
     */
    public function forRunOutput(): array
    {
        return array_filter([
            'published' => $this->published,
            'refusal' => $this->refusal?->value,
            'tier' => $this->tier?->value,
            'site_change_id' => $this->siteChangeId,
            'advisory' => $this->advisory === null ? null : true,
        ], static fn (mixed $value): bool => $value !== null && $value !== false);
    }
}
