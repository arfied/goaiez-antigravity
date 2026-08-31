<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Content\ContentQuality;

/**
 * Why the quality gate refused a page — the vocabulary stored in
 * `content_quality_checks.failure_reasons`.
 *
 * ⛔ **A CLOSED VOCABULARY BECAUSE THE GATE HAS TO BE EXPLAINABLE.**
 * `BUILD-PLAN` §2.11.3 slice C: *"fail → hold with reasons persisted, so the
 * gate is explainable"*. A free-form string would let the reason drift into
 * whatever the calling code happened to say that day, and a held page whose
 * reason nobody can render is a silent refusal with extra storage.
 *
 * ⛔ **A MODERATION REFUSAL IS NOT IN THIS LIST AND MUST NEVER BE ADDED TO IT**
 * — decision 347, transplanted. A classifier declining to read text is a
 * successful call that produced no verdict; the page is held for a person, and
 * filing it as a failure would send somebody to check a vendor that is fine and
 * would tell a tenant their copy broke a rule it did not break. That state is
 * `passed IS NULL` plus `unavailable_reason`, and
 * {@see ContentQuality} is where the two are kept apart.
 *
 * ⚠️ **`uniqueness_below_threshold` IS SPELLED EXACTLY AS `ContentQualityCheckFactory`
 * ALREADY SPELLED IT.** That factory has existed since Stage 0 with no writer
 * behind it, and its `failing()` state is the one string this table has ever
 * held. Renaming it would have made every pre-existing fixture describe a reason
 * this enum has no case for.
 */
enum ContentQualityFailure: string
{
    /**
     * Too much of this page already exists on the tenant's own site.
     * `16` §15.3: *"≥60% content unique vs. every other page on the site"*.
     */
    case UniquenessBelowThreshold = 'uniqueness_below_threshold';

    /**
     * `16` §15.3: *"contains ≥2 pieces of genuine first-party data"*.
     */
    case InsufficientFirstPartyData = 'insufficient_first_party_data';

    /**
     * `16` §15.3: *"answers a question with demonstrated real demand"*.
     */
    case NoDemandEvidence = 'no_demand_evidence';

    /**
     * `29` §9.1: *"target reading level 8"*; doc `33`: *"readability grade ≤8
     * for consumer topics"*.
     */
    case ReadingGradeTooHigh = 'reading_grade_too_high';

    /**
     * A classifier read the copy and asked for it to be withheld.
     *
     * ⚠️ **THE FLAGS THEMSELVES ARE NOT STORED HERE.** They describe text this
     * platform wrote and would have published under a tenant's own name, and one
     * word — "held, because a classifier objected" — is what an owner acts on.
     * The wider record is the `ai_calls` row the router already writes.
     */
    case ModerationFlagged = 'moderation_flagged';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * What to tell the owner, in the words `22` asks for: what happened to the
     * thing they care about, never how this system is built.
     */
    public function sentence(): string
    {
        return match ($this) {
            self::UniquenessBelowThreshold => 'Too much of this page repeats pages you already have.',
            self::InsufficientFirstPartyData => 'This page needs at least two real details from your business.',
            self::NoDemandEvidence => 'Nobody appears to be asking about this.',
            self::ReadingGradeTooHigh => 'This reads as harder work than most of your customers will want.',
            self::ModerationFlagged => 'Some of this wording should not go out under your name.',
        };
    }
}
