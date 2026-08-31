<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Billing\PlanCharges;
use App\Services\Billing\Subscriptions;
use App\Support\LegalCanon;
use App\Support\Messaging\LifecycleLadderCatalog;

/**
 * Every rung of the lifecycle ladder — R32's usage and trial sets, plus the
 * PIII-29/30 trial beats that carry a written promise (CC-5 §2).
 *
 * ⚠️ **THE ENUM IS THE KEY SET AND THAT IS DELIBERATE.** CC-6 turns the seeded
 * templates into an architecture test *"iterating every seeded template key"*,
 * so the keys have to be enumerable rather than discovered by scanning an array.
 * `cases()` is the enumeration, {@see LifecycleLadderCatalog} is the copy, and a
 * rung added here without copy fails loudly rather than rendering an empty
 * message.
 *
 * ## ⛔ THE WORD LAW LIVES ON THIS ENUM AS WELL AS IN THE STRINGS
 *
 * R48c: **"paused", never "expired"** — a trial that has run out has *paused*,
 * because the account, its settings, its conversations and its credits are all
 * still there and one minute of somebody's time brings them back. So the case
 * below is `TrialEnded` and its value is `trial_ended`.
 * `tests/Feature/Messaging/MessageCanonTest.php`'s *"the word 'expired' appears
 * in no seeded customer-facing string"* greps every seeded string for the
 * forbidden word; the naming here is the same rule applied where a grep would
 * not reach, because a case called `TrialExpired` is what the next person
 * writing copy reads first.
 *
 * ⛔ **THIS CITED A `LifecycleLadderTest` AND NO FILE OF THAT NAME HAS EVER
 * EXISTED IN THIS REPOSITORY — CORRECTED 2026-08-25 (9661).** The same phantom
 * was corrected in `LifecycleLadderCatalog` at 9466 and these two were left
 * because `app/Enums/` was nobody's file that wave.
 */
enum LifecycleRung: string
{
    /** R32's usage ladder, 75% of the month's credits used. */
    case UsageSeventyFive = 'usage_75';

    /** R32's usage ladder, 90% used. */
    case UsageNinety = 'usage_90';

    /** R32's usage ladder, all of it used — service **paused**. */
    case UsageExhausted = 'usage_100';

    /** PIII-29 D7 — halfway, and the first rung that carries the promise. */
    case TrialDaySeven = 'trial_day_7';

    /** R32's trial ladder, day 10. */
    case TrialDayTen = 'trial_day_10';

    /** R32's trial ladder, day 13 — the last day but one. */
    case TrialDayThirteen = 'trial_day_13';

    /** R32's trial ladder, the landing. **Paused, not gone.** */
    case TrialEnded = 'trial_ended';

    /**
     * Whether this rung carries R39's written guarantee.
     *
     * CC-5 §2: *"day-7 and expiry rungs carry the guarantee reminder line (bind
     * the sentence via CC-4's one-source key, never paste it)"*. R39's own rider
     * says the same and adds *"No other rung changes"*, which is why this is a
     * predicate on two cases rather than a flag somebody sets per message.
     *
     * ⛔ **THE SENTENCE ITSELF IS NEVER IN THIS FILE OR THE CATALOGUE.** It is
     * `legal.guarantee_sentence`, read at compose time — see {@see LegalCanon}.
     * R39 writes one wording and PIII-29 D7 writes another; pasting either would
     * make this repository the third source of a promise that has to have one.
     */
    public function carriesTheGuarantee(): bool
    {
        return match ($this) {
            self::TrialDaySeven, self::TrialEnded => true,
            self::UsageSeventyFive, self::UsageNinety, self::UsageExhausted, self::TrialDayTen, self::TrialDayThirteen => false,
        };
    }

    /**
     * Whether this rung has an SMS line at all.
     *
     * ⚠️ **D7 IS EMAIL ONLY, AND R32's CHANNEL LAW IS WHY.** PIII-29 §1: *"email
     * carries the story · SMS is one line + one link … Never the same message on
     * two channels the same day."* D7 is the founder-math beat and does not fit
     * one line; R32 authored no SMS for it, and inventing one would be this
     * codebase writing a text message to a member of the public.
     */
    public function hasText(): bool
    {
        return $this !== self::TrialDaySeven;
    }

    /**
     * Whether this rung belongs to the trial ladder rather than the usage one.
     *
     * ⚠️ **DERIVED FROM THE SCHEDULE AND NEVER LISTED SEPARATELY.** A second
     * hand-written list of "the trial rungs" is a set that goes out of step with
     * {@see self::daysRemainingAtSend()} the first time somebody adds a beat, and
     * the drift would be silent: the sweep would iterate one set and the lints
     * another.
     */
    public function isTrialRung(): bool
    {
        return $this->daysRemainingAtSend(self::CANONICAL_TRIAL_DAYS) !== null;
    }

    /**
     * The trial ladder, in the order it was authored.
     *
     * ⚠️ **AUTHORING ORDER, NOT FIRING ORDER, AND THE TWO COME APART.** At
     * `billing.trial_days = 7` the day-10 rung's four-days-remaining trigger is
     * reached *before* the halfway rung's, so anything that needs "which rung
     * came earlier in this tenant's trial" must compare
     * {@see self::daysRemainingAtSend()} at the length actually configured, and
     * never the position in this list. {@see Subscriptions::claimTrialRung()}
     * is the one place that distinction is load-bearing.
     *
     * @return list<self>
     */
    public static function trialRungs(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $rung): bool => $rung->isTrialRung(),
        ));
    }

    /**
     * The fourteen-day trial these case names were written against.
     *
     * ⛔ **NOT A DEFAULT AND NEVER A FALLBACK.** `billing.trial_days` is the
     * configured length and {@see PlanCharges::trialDays()}
     * is its only behavioural reader; this constant exists so that
     * {@see self::isTrialRung()} can ask a question about *shape* without
     * reaching the registry, and so a lint can pin each case's **name** against
     * its trigger at the length the name describes. Reading it as the trial
     * length anywhere else would be a price term invented at a call site.
     */
    public const int CANONICAL_TRIAL_DAYS = 14;

    /**
     * How many whole days are left of the trial when this rung goes out.
     *
     * ⛔ **THE LADDER IS ANCHORED TO DAYS REMAINING AND NOT TO DAYS ELAPSED, AND
     * THAT IS WHAT KEEPS THE AUTHORED COPY TRUE AT ANY TRIAL LENGTH** (9397).
     * Three of these four rungs make a claim about *time left*: "4 days left",
     * "Tomorrow it pauses", "Last day tomorrow!". Fired on an absolute day
     * counted from registration, every one of them becomes false the moment
     * `billing.trial_days` moves — which is decision 512's failure exactly, and
     * `resources/views/marketing/home.blade.php` carries the comment about the
     * last time this application promised a trial length it no longer offered.
     * Fired on days remaining, all three are true by construction at 7 days, at
     * 14, at 30.
     *
     * ⛔ **THE CASE NAMES ARE THE FOURTEEN-DAY INSTANCE OF THIS SCHEDULE AND ARE
     * NOT THE SCHEDULE.** `TrialDayTen` fires at four days remaining, which is
     * day ten of a fourteen-day trial and day three of a seven-day one.
     * `tests/Feature/TrialReminderTest.php`'s *"each case name describes the day
     * it fires on a fourteen-day trial"* pins each name against its trigger at
     * {@see self::CANONICAL_TRIAL_DAYS}, so the names cannot quietly stop
     * describing the beats. ⛔ **THIS TOO SAID `LifecycleLadderTest`, WHICH HAS
     * NEVER EXISTED — CORRECTED 2026-08-25 (9661).**
     *
     * ⚠️ **HALFWAY IS THE ONE THAT HAS TO BE COMPUTED**, because its copy says
     * *"Halfway."* rather than a number, and no slot can carry that word. On an
     * odd length `intdiv()` puts it on the first day past the midpoint, which is
     * the closest a whole day gets.
     *
     * ⛔ **THE LANDING RUNG IS `-1` AND NOT `0`, AND THE DIFFERENCE IS A TENSE.**
     * Its subject is *"Paused, not gone"* — a statement that the pause has
     * already happened. The trial ends at `businesses.created_at + trialDays`,
     * which is an instant partway through the calendar day on which zero days
     * remain, so a sweep that ran that morning would announce a pause that had
     * not happened yet. One day later the whole of that day has passed and the
     * sentence is true for everybody. ⚠️ **It is equality and never `<= -1`**,
     * which is what stops the first scheduled run mailing *"Paused, not gone"*
     * to every account whose trial ran out months ago.
     *
     * @return ?int null where this rung is not on the trial ladder at all
     */
    public function daysRemainingAtSend(int $trialDays): ?int
    {
        return match ($this) {
            self::TrialDaySeven => intdiv($trialDays, 2),
            self::TrialDayTen => 4,
            self::TrialDayThirteen => 1,
            self::TrialEnded => -1,
            self::UsageSeventyFive, self::UsageNinety, self::UsageExhausted => null,
        };
    }
}
