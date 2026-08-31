<?php

declare(strict_types=1);

namespace App\Services\Proof;

use App\Enums\ReviewSource;
use App\Enums\ReviewSyncAbsenceReason;
use App\Models\ProofNumber;
use App\Models\Review;
use App\Services\Gbp\GbpConnections;
use App\Services\Gbp\GoogleReviewIngest;
use App\Services\Reviews\ReplyPublicationStatus;
use App\Services\Reviews\ReviewRouter;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The one place `28` §3.3's three numbers are computed.
 *
 * ⚠️ **READ §3.3's INTEGRITY RULE BEFORE CHANGING ANYTHING HERE**: *"proof
 * numbers are computed only from auditable rows … No estimates, no modeled
 * numbers, ever. If a number would be zero, show zero — the First 7-Day path
 * exists to fix that, not the copy."* That sentence is the whole design. A
 * number on the owner's home screen is the most-trusted thing this product says,
 * and an inflated one is never discovered by the person it misleads.
 *
 * ## What each number actually counts, and where it is narrower than §3.3
 *
 * **Google reviews** — `reviews` with `source = 'google'`. The cheap
 * implementation counts `reviews` regardless of source, which would report a
 * tenant's own feedback form as reviews on their Google listing — a false claim
 * about a third party's property, made to the one person least able to check
 * it.
 *
 * ⛔ **THIS SAID *"ZERO FOR EVERY TENANT ALIVE TODAY, BECAUSE NOTHING WRITES
 * THAT SOURCE: INGEST IS ROW 3 SLICE I AND WAITS ON THE GBP APPROVAL"* AND
 * EVERY CLAUSE OF IT IS FALSE — CORRECTED 2026-08-26 (9915).**
 * {@see GoogleReviewIngest::upsertOne()} writes
 * `source => ReviewSource::Google` and has done since row 3 slice I shipped;
 * `gbp:sync` has been on the schedule every fifteen minutes since 2026-08-10;
 * {@see GbpConnections::complete()} dispatches a backfill
 * the moment an owner connects; and the owner set `gbp.zernio_enabled` to
 * `true` in production on 2026-08-21. **So the count moves for a connected
 * tenant, and the reason it reads zero for anybody else is that they have not
 * connected Google — never that this application cannot read it.**
 *
 * ⚠️ **THE OLD READING IS KEPT BECAUSE OF WHAT IT COST.** Decision 7044 caught
 * exactly this sentence being relayed into a wave brief — *"a lane briefed on
 * this would have built a writer that has existed for eleven days"* — and
 * corrected the brief. **It did not correct this docblock, or the owner-facing
 * sentence in {@see self::definitions()} that was written from the same
 * premise**, so a true-when-written scope note went on telling every reader,
 * and every tenant, that the feature did not exist. That is the whole of 9915.
 *
 * ⚠️ **AND *"the count moves"* IS NOT *"reviews have arrived"*.** Whether any
 * deployed tenant has ever had a Google review ingested is a fact about
 * production data and about Google's own listing verification, and **nothing in
 * this repository can answer it** — so neither this docblock nor
 * {@see self::notes()} may claim a listing is being read successfully. What
 * they may state is the state of the machinery, which is what they state.
 *
 * **Leads** — §3.3 says *"inbound calls answered or texted-back + forms captured
 * + bookings made"*. Calls are Stage 2 and bookings do not exist, so v1 counts
 * forms captured: first-party submissions. `29` §11.2 row 5 names this scope
 * exactly — *"Proof numbers v1 (reviews+forms)"* — so it is the specification's
 * own narrowing rather than ours. ⚠️ **A flagged submission still counts**: the
 * form was captured, the lead is real, and moderation decides whether the words
 * are published, not whether the person existed.
 *
 * **Recovered** — §3.3 says triage that reached `resolved` *"with the customer
 * confirmed satisfied"*. ⚠️ **Nothing records the customer's confirmation.**
 * `triage_conversations` carries a status and a free-text `resolution`, and
 * treating `resolved` as "confirmed satisfied" is exactly the modeled number the
 * same paragraph forbids. So this counts `status = 'resolved'`, and
 * {@see self::definitions()} states that to the owner instead of §3.3's
 * sentence — because §3.3 also says the definitions are shown on tap, and a
 * shown definition that does not match the computation is worse than a narrow
 * number.
 *
 * ✅ **AND IT CAN MOVE OFF ZERO SINCE 2026-08-12 (2689).** ⚠️ **This paragraph
 * described a computation that could never return anything but 0 and did not
 * say so**, which is the shape 256 warns about wearing a docblock: nothing in
 * `app/` wrote any `TriageStatus` but `Open`, so the branch counting `Resolved`
 * was unreachable and the most-trusted number on the owner's Home screen was a
 * structural zero rendered as a fact. `Livewire\Account\WinBack` is the writer,
 * and {@see ReviewRouter::recordTriageOutcome()} refuses a
 * recovery with no `resolution` — so the number is at least as strong as a
 * sentence the person who made the call had to write, which is the closest this
 * schema gets to §3.3's *"confirmed satisfied"* and is still narrower than it.
 *
 * ## Periods
 *
 * `'all'` or `'YYYY-MM'`. ⚠️ **A row with no `created_at` is counted in all-time
 * and in no month.** Forty of forty-three tables carry a nullable `created_at`
 * (Laravel's own `timestamps()` default), and a month bucket cannot honestly
 * claim an undated row — guessing which month it belonged to is a modeled
 * number. "Ever" stays true regardless of when.
 */
final class ProofNumbers
{
    public const string ALL = ProofNumber::ALL;

    public function __construct(private readonly ReviewRouter $router) {}

    /**
     * Recompute one period from source rows and persist it.
     *
     * Idempotent by (business_id, period): a second run updates the row rather
     * than inserting a second one, which would double every number on the screen
     * and look like growth.
     */
    public function recompute(string $period): ProofNumber
    {
        $businessId = Tenancy::idOrFail();

        $this->assertPeriod($period);

        [$from, $to] = $this->window($period);

        $counts = [
            'google_reviews' => $this->reviewsFrom(ReviewSource::Google, $from, $to)->count(),
            'leads' => $this->reviewsFrom(ReviewSource::FirstParty, $from, $to)->count(),
            // ⚠️ THROUGH `ReviewRouter`, NEVER `TriageConversation` DIRECTLY. An
            // `ArchitectureTest` lint holds that model to the one service that
            // opens conversations, and it refused this reader — correctly.
            // Decision 624's rule: ask whether the caller belongs behind the
            // service before widening an allowlist. Allowlisting this file would
            // have granted it the write surface too.
            'recovered' => $this->router->recoveredCount($from, $to),
        ];

        return DB::transaction(function () use ($businessId, $period, $counts): ProofNumber {
            $row = ProofNumber::query()->firstOrNew(['period' => $period]);

            // Guarded columns, so `forceFill` rather than `fill` — the guard is
            // there to stop a *caller* authoring a number, not to stop the one
            // service that derives it.
            $row->forceFill($counts + [
                'business_id' => $businessId,
                'period' => $period,
                'computed_at' => now(),
            ])->save();

            return $row;
        });
    }

    /**
     * The stored numbers, or an unsaved zero row when none has been computed.
     *
     * ⚠️ **A ZERO ROW, NEVER NULL.** §3.3's rule is that a zero renders as zero,
     * and returning null hands every caller the chance to render a dash, a
     * spinner or "no data yet" — the three things that sentence exists to
     * forbid. A tenant who has never been rolled up has three real zeros.
     */
    public function for(string $period): ProofNumber
    {
        $this->assertPeriod($period);

        $stored = ProofNumber::query()->where('period', $period)->first();

        if ($stored instanceof ProofNumber) {
            return $stored;
        }

        return (new ProofNumber)->forceFill([
            'business_id' => Tenancy::idOrFail(),
            'period' => $period,
            'google_reviews' => 0,
            'leads' => 0,
            'recovered' => 0,
            'computed_at' => null,
        ]);
    }

    /**
     * The period string for a moment — `'YYYY-MM'`.
     */
    public static function monthOf(?Carbon $at = null): string
    {
        return ($at ?? now())->format('Y-m');
    }

    /**
     * Which periods a sweep at this moment owes a tenant.
     *
     * Always the two `Livewire\Account\Home` can render — `'all'` and the month
     * in progress — because those are the only two a toggle with *"no other
     * options in Normal"* (§3.3) will ever ask for.
     *
     * ⚠️ **AND, ON THE FIRST OF A MONTH, THE MONTH THAT HAS JUST CLOSED,
     * BECAUSE NOTHING ELSE WILL EVER REVISIT IT.** From the 1st onward the
     * sweep's "month in progress" is the new one, so August's row is frozen at
     * whatever the last sweep before midnight computed and is short by every
     * capture, review and recovery in that final gap. **The row is written
     * either way** — the sweep has been maintaining it all month — so the
     * choice is not whether a closed month is stored but whether the stored
     * figure is the true one, and a rollup that is silently a little low is
     * §3.3's modeled number arriving by omission.
     *
     * ⚠️ **NOTHING READS A CLOSED MONTH TODAY AND THAT IS DELIBERATELY NOT AN
     * ARGUMENT AGAINST COMPUTING IT.** `Home` offers this month and all time
     * and no way to reach August; the reader that wants it is `29` §19.2's
     * monthly report, which is a module and is not this slice. Leaving the
     * figure wrong until that module arrives would hand it a row that looks
     * computed, carries a `computed_at`, and understates the tenant's month —
     * 903's trap exactly, *"an estimate with a timestamp on it"*.
     *
     * ⚠️ **THE DAY IS THE WHOLE OF THE FIRST**, not the first tick of it. The
     * sweep runs hourly, so a closed month gets twenty-four chances to be
     * finished off. Recomputing an unchanging month twenty-three more times
     * costs three `COUNT(*)`s a time and is idempotent by (business_id,
     * period), which is a better trade than a stored "have I closed this month
     * yet" flag — a second piece of state that can be wrong on its own.
     *
     * ⛔ **THIS SAID THE TWENTY-FOUR CHANCES WERE UNDERWRITTEN — *"missing all
     * of them takes an outage long enough for `OperatorAlertKind::HeartbeatSilent`
     * to have fired"* — AND FOR THE FAILURE MOST LIKELY TO HAPPEN THAT IS FALSE
     * (9919, 2026-08-26).** `RecomputeProofNumbersJob` is dispatched **per
     * tenant**, so a permanent failure caused by **one tenant's own data**
     * leaves every other tenant's sweep succeeding, the worker healthy and the
     * heartbeat beating. Nothing rings: what it produces is one `failed_jobs`
     * row an hour on a path whose entire volume sits far below
     * `ops.failed_job_spike`'s threshold, which is `CLAUDE.md`'s own *"a path
     * whose whole volume is below the threshold is not watched by it, however
     * completely it has stopped."* The customer-visible consequence is that
     * **that** tenant's *"This month"* freezes and their *"Worked out …"*
     * timestamp goes stale in public while every instrument reads green.
     *
     * ⚠️ **The twenty-four chances are still a real argument and are kept** —
     * they cover the platform-wide outage, which is the case the sentence above
     * was reaching for. What they do not cover is the per-tenant one, and the
     * repair is a `failed()` hook on the job rather than a bell.
     * ⛔ **DELIBERATELY NOT BUILT HERE** (9920): `RecomputeProofNumbersJob` is
     * another lane's file this wave, and whether these jobs owe `failed()` hooks
     * at all is a live argument that wave 30 refused once. **Raised, sized and
     * left**, which is the honest state — not asserted away by the sentence this
     * replaces.
     *
     * @return list<string>
     */
    public static function periodsDueAt(?Carbon $at = null): array
    {
        $at ??= now();

        $due = [self::ALL, self::monthOf($at)];

        if ((int) $at->day === 1) {
            // `startOfMonth()` before `subMonthNoOverflow()` rather than after:
            // this only runs on the 1st, so the two orders agree today, and the
            // one that stays right if that ever changes is the one that lands
            // on a day every month has.
            $due[] = self::monthOf($at->copy()->startOfMonth()->subMonthNoOverflow());
        }

        return $due;
    }

    /**
     * What each number means, in the owner's words, shown on tap (§3.3).
     *
     * ⚠️ **These strings are part of the integrity rule, not decoration.** They
     * are what makes a narrower computation honest rather than misleading, so
     * changing a count without changing its sentence here is the defect this
     * method exists to prevent.
     *
     * ⛔ **AND IT DID NOT PREVENT ITS OWN — SO THE METHOD'S SUBJECT IS NARROWED
     * RATHER THAN ITS PROMISE RESTATED** (9916, 2026-08-26). The Google
     * sentence carried a second clause — *"We cannot read your Google reviews
     * yet, so this stays at zero for now"* — which is **not a definition of
     * what the number counts**. It is a claim about the state of a deployment:
     * about a registry row, a scheduled command and whether a tenant has
     * connected an account. The count never changed, so the rule above — *do
     * not change a count without changing its sentence* — was never triggered,
     * **and no test can compare a hard-coded string against a deployment
     * anyway.** The guard's subject was `count ↔ sentence`; the defect's
     * subject was `sentence ↔ world`.
     *
     * ⛔ **THE REPAIR IS THE SPLIT, NOT BETTER WORDING.** What survives here is
     * only what is true of the *computation* and is therefore true on every
     * deployment for ever — §3.3's *"locked"* definitions. Anything that is
     * true today because of how the platform is configured belongs in
     * {@see self::notes()}, which is derived from the configuration at render
     * time and cannot go stale in a literal.
     *
     * ⚠️ **`leads` KEEPS ITS SECOND SENTENCE AND THAT IS NOT AN INCONSISTENCY.**
     * *"Calls and bookings are not counted yet"* is a statement about the
     * **scope of the count** — this figure is `ReviewSource::FirstParty` rows
     * and nothing else — which is exactly what a locked definition is for. The
     * test of which side a sentence belongs on is whether flipping a registry
     * row or connecting an account could falsify it: `leads`' cannot, and the
     * Google one was falsified by both.
     *
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            'google_reviews' => 'Reviews first seen on your Google listing.',
            'leads' => 'People who filled in your feedback form. '
                .'Calls and bookings are not counted yet.',
            // ⚠️ THE WORDS ARE THE SCREEN'S AND THE FEED'S, NOT A THIRD SET
            // (2710). This read *"whose conversation you marked resolved"* when
            // no screen existed to mark one — `resolved` was our column name,
            // shown to an owner who had never seen it. The control now says
            // "Mark as won back", the card then reads "Won back", and
            // `AutopilotActionType::TriageResolved` has said *"Won back an
            // unhappy customer"* all along. One event, one vocabulary.
            'recovered' => 'Unhappy customers you marked as won back on Win back customers.',
        ];
    }

    /**
     * Why a number cannot move for this tenant right now — keyed as
     * {@see self::definitions()}, and **absent for every number that can move**.
     *
     * ⛔ **A NOTE IS RENDERED ONLY WHERE THERE IS SOMETHING TRUE TO SAY, AND
     * THERE IS NEVER A POSITIVE ONE** (9915). There is no *"we are reading your
     * listing"* arm and there must not be one: this application can prove that
     * the sweep is switched on and that an account is connected, and it cannot
     * prove that Google answered, that the listing is verified, or that a single
     * review has ever come back. **Asserting the capability is the failure this
     * whole method exists to undo** — see the class docblock — so the absence of
     * a note means only *nothing here is stopping it*, and the number itself is
     * left to make the claim.
     *
     * ⚠️ **THE LADDER IS ORDERED AND THE ORDER IS LOAD-BEARING**, for
     * {@see ReplyPublicationStatus}'s own reason: the
     * switch outranks the connection, because `Account\Connections` gates its
     * connect control on the same registry key, so telling an owner to connect
     * Google while it is off sends them to a screen reading *"Not open yet"*.
     *
     * ⛔ **THE THIRD RUNG IS REQUIRED RATHER THAN DEFAULTED TO NULL**
     * (10120–10139). `$googleReadAbsence` has no default on purpose: a default
     * of null is fail-open on the exact silence it exists to end, and a caller
     * that forgot it would compile and go on rendering a bare number.
     * ⚠️ **It is named in backticks in the `@param` rather than `{@see}`d**, on
     * this class's own rule — a `{@see}` becomes an import, and an import reads
     * as a dependency `ProofNumbers` deliberately does not have (9917).
     *
     * ⚠️ **NEITHER SENTENCE SAYS *"stays at zero"***, which the one they replace
     * did. A tenant who connected Google, had reviews ingested and then
     * disconnected has a **non-zero** count and no reading, and *"stays at
     * zero"* would be a second false statement arriving by the same route as the
     * first — a claim about the world written as though it were a claim about
     * the count.
     *
     * @param  bool  $googleReadingIsOpen  `gbp.zernio_enabled` — read by the
     *                                     caller, not here, so that the hourly
     *                                     recompute path never constructs the
     *                                     registry or the connection store.
     * @param  bool  $googleIsConnected  Whether `gbp:sync` would reach this
     *                                   tenant at all — {@see GbpConnections::hasUsableConnection()},
     *                                   which shares its predicate with the
     *                                   sweep's own enumeration so the sentence
     *                                   and the sweep cannot disagree.
     * @param  ?ReviewSyncAbsenceReason  $googleReadAbsence  Why the most recent
     *                                                       `reviews.google_sync` run read nothing, across every
     *                                                       location the sweep reaches. Read by the caller, for the
     *                                                       reason above.
     * @return array<string, string>
     */
    public static function notes(
        bool $googleReadingIsOpen,
        bool $googleIsConnected,
        ?ReviewSyncAbsenceReason $googleReadAbsence,
    ): array {
        if (! $googleReadingIsOpen) {
            return [
                'google_reviews' => 'Reading Google reviews is not open yet, so this number will not change.',
            ];
        }

        if (! $googleIsConnected) {
            return [
                'google_reviews' => 'We are not reading your Google listing yet — connect Google on Google reviews and we will start counting these.',
            ];
        }

        // ⛔ **THE THIRD RUNG, AND THE HOLE THE FIRST TWO LEFT** (10120–10139).
        // A connected tenant whose last sync failed, whose listing Zernio
        // refused, or whose backfill is sitting in `failed_jobs` fell straight
        // through to `return []` and read a bare `0` with nothing beside it —
        // **the same screen, to the character, as a tenant whose customers
        // simply have not left any reviews.** The job had recorded which of the
        // two it was, on every run, since the day it shipped.
        //
        // ⚠️ **STILL NOT A POSITIVE ARM** (9921). This says why a read did not
        // happen; it never says one did. The null case below is unchanged and
        // means only *nothing here is stopping it*.
        if ($googleReadAbsence !== null) {
            return ['google_reviews' => $googleReadAbsence->sentence()];
        }

        return [];
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function window(string $period): array
    {
        if ($period === self::ALL) {
            return [null, null];
        }

        // `parse` rather than `createFromFormat`, which is typed nullable and
        // would need a cast or an assert to satisfy Larastan — both of which the
        // analyser's own instructions forbid as ways to silence it. The period
        // has already been through `assertPeriod()`, so this cannot fail.
        $start = Carbon::parse($period.'-01')->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }

    /**
     * @return Builder<Review>
     */
    private function reviewsFrom(ReviewSource $source, ?Carbon $from, ?Carbon $to): Builder
    {
        $query = Review::query()->where('source', $source);

        // ⚠️ `created_at` is when *we* first saw it, never `review_create_time`,
        // which is when the customer wrote it on the platform. Using the latter
        // would let a backfill of old Google reviews retroactively rewrite months
        // the owner has already read — numbers that change after the fact are
        // the thing a proof screen cannot survive.
        // ⚠️ `whereNotNull` IS EXPLICITNESS, NOT PROTECTION, AND SAYING SO IS THE
        // POINT. `created_at BETWEEN a AND b` already excludes NULL in SQL —
        // mutation confirmed removing it changes nothing — so the clause states
        // an intent the next reader would otherwise have to derive from
        // three-valued logic. Decision 809 is the reason it is described
        // accurately rather than left to read as a guard: calling something
        // load-bearing when it is not invites the inverse mistake later.
        return $from === null
            ? $query
            : $query->whereNotNull('created_at')->whereBetween('created_at', [$from, $to]);
    }

    private function assertPeriod(string $period): void
    {
        if ($period === self::ALL) {
            return;
        }

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) !== 1) {
            throw new InvalidArgumentException(
                "'{$period}' is not a proof-number period. Use 'all' or a month as 'YYYY-MM'.",
            );
        }
    }
}
