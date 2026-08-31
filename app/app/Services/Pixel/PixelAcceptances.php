<?php

declare(strict_types=1);

namespace App\Services\Pixel;

use App\Http\Requests\Api\StorePixelBatchRequest;
use App\Models\PixelMonthlyUsage;
use App\Services\Warehouse\PixelArrivals;
use App\Services\Warehouse\WarehouseRetention;
use Carbon\CarbonImmutable;

/**
 * Whether this tenant's own pixel got anything *past the collector's gates*
 * recently — the half of the question {@see PixelArrivals}
 * cannot answer, and the only thing that tells a website nobody visited apart
 * from a website we took the visits from and then lost (9900).
 *
 * ---------------------------------------------------------------------------
 * ⛔ THE DEFECT THIS CLASS EXISTS FOR: THE VISIT THAT NEVER HAPPENED
 * ---------------------------------------------------------------------------
 * [[\App\Jobs\ArchivePixelBatchJob]] stores the L0 object **first** and derives
 * the event layer **second**, in that order, in one method. So an archive that
 * throws writes no derived row either (9844) — and `PixelArrivals`, which reads
 * that derived layer, answers `null`. `PixelCollectionState::Listening` then
 * told the owner, in their own words: *"Nothing has been turned away and
 * nothing has reached us. A website has to be visited before there is anything
 * to send."*
 *
 * ⛔ **THAT IS A CLAIM ABOUT THE TENANT'S WEBSITE AND THEIR VISITORS, MADE OUT
 * OF A FACT ABOUT OUR OWN OBJECT STORE.** It is not hypothetical: `Storage::
 * disk('s3')` could not be built on any deployment between 2026-08-18 and
 * 2026-08-25 (9408, 9421), so for a week every accepted beacon spent three
 * attempts and landed in `failed_jobs` — and the one screen the owner opens to
 * find out whether their install worked told them nobody had come.
 *
 * ---------------------------------------------------------------------------
 * ⛔ WHAT THE COUNTER IS, EXACTLY, AND WHAT IT IS NOT
 * ---------------------------------------------------------------------------
 * `pixel_monthly_usage.events_total` is written by
 * {@see MonthlyEventCap::admit()} on the **admitted** arm only — the refused
 * arm moves `events_dropped` and leaves this column alone — so a non-zero value
 * means *at least one batch of this tenant's events passed §11 rows 1, 2, 9(a)
 * and 4*: a live public key, an origin their own account lists, a
 * non-`Phi` data classification, and the monthly event cap.
 *
 *  1. ⛔ **IT IS NOT A COUNT OF WHAT WAS ARCHIVED, AND MAY NEVER BE READ AS
 *     ONE.** Two gates run *after* it — the Global Privacy Control cross-check
 *     and the form-value refusal — and both drop the batch before it is
 *     dispatched. So this is an **upper bound** on what was sent to the queue.
 *     A sentence built on it may say *"your visits reached us"*; it may never
 *     say *"we kept them"* or *"they are on their way"*.
 *  2. ⚠️ **IT COUNTS EVENTS, NOT BEACONS.** One request carries up to
 *     {@see StorePixelBatchRequest::MAX_EVENTS} of them,
 *     so it cannot be differenced against a count of L0 objects and this class
 *     deliberately returns a boolean rather than a number — see
 *     {@see self::acceptedRecently()}.
 *  3. ⚠️ **IT IS MONTHLY.** It cannot say whether a particular day's archive is
 *     complete, which is the question 9848 raised, costed and left open.
 *  4. ⛔ **A `Phi` TENANT IS REFUSED BEFORE IT AND LEAVES NO ROW ANYWHERE.**
 *     `PixelCollector` runs §11 row 9(a) ahead of every gate that writes
 *     something, deliberately (*"a tenant refused wholesale must be refused
 *     before anything is written about it"*), so a health-classified account
 *     whose website is busy reads here exactly like an account nobody has
 *     visited. **That hole is real, it is the same shape as the defect this
 *     class closes, and it is recorded rather than papered over** (9908): no
 *     such tenant can be onboarded at all while rule 24's KMS key waits on
 *     Stage 3, and inventing copy for a state no account can occupy is 256's
 *     vacuity with a sentence attached.
 *
 * ---------------------------------------------------------------------------
 * ⛔ THIS COUNTER WAS EXAMINED AND REJECTED ONCE ALREADY, AND THE OBJECTION IS
 * RIGHT ABOUT THE CLAIM IT WAS ANSWERING (9905)
 * ---------------------------------------------------------------------------
 * `tests/Feature/Pixel/PixelArrivalsTest.php`'s *"the monthly counter moves on
 * a refused batch and this reader does not"* calls it **the decoy**: *"It is
 * the nearest thing in the schema to an arrival signal and it is the wrong
 * one; a screen wired to it would tell a tenant their data was arriving on the
 * strength of a batch that was thrown away for carrying a form value."*
 * ⚠️ **That is correct and this class does not contradict it.** *Their data is
 * arriving* is the claim that may only ever be made from
 * [[\App\Services\Warehouse\PixelArrivals]], and nothing here is permitted to
 * make it. What this answers is a strictly weaker question — *did anything of
 * theirs get through the door* — which is exactly the question that decides
 * whether silence downstream is **their** fact or **ours**.
 *
 * ⚠️ **THE RESIDUAL COST, STATED RATHER THAN WAVED AWAY.** On the two arms the
 * decoy names — a batch refused for a form value, and a batch refused for
 * violating a genuine `Sec-GPC` signal — a screen reading this will say *"your
 * visits reached us and are not showing"*, which is true, and invite the owner
 * to tell us, which produces a support contact for a refusal that is working
 * as designed. ⚠️ **Both arms mean something that is not our current bundle is
 * posting with this tenant's key**: the bundle this platform actually ships
 * reads `navigator.globalPrivacyControl` and stamps `gpc_optout` itself, and
 * reads no form value at all. ⚠️ **AND THIS PARAGRAPH MAY NOT NAME THAT
 * BUNDLE'S PATH** (5055) — `Architecture/PixelTest`'s delivery tripwire matches
 * raw file contents, comments included, and an earlier draft of this sentence
 * reddened the build naming this file. **Reworded rather than excepted**, which
 * is 4977's answer to the identical event: an exception for a mere mention is
 * 511's failure, and rewording costs nothing. **So the contact is not a false alarm so much as a
 * differently-named one**, and it is a cheaper failure than telling somebody
 * with a busy website that nobody visited it.
 *
 * ---------------------------------------------------------------------------
 * ⛔ AND FOUR ARTEFACTS SAY THIS COUNTER DOES NOT EXIST — 2026-08-26 (9903)
 * ---------------------------------------------------------------------------
 * *"This platform counts accepted beacons nowhere"* is written in
 * [[\App\Enums\OperatorAlertKind]], in `Admin\OperatorAlertBoard`, in
 * `Replayer::warnIfTheArchiveWasFailing()` and in decisions 9846 and 9848 —
 * the last of which costs a counter that *"does not exist"* as *"a per-tenant
 * per-day write on the hottest path in the product"*. ⚠️ **The write is
 * already there and has been since 2026-08-18**: one upsert per accepted
 * batch, on that same hot path, in `MonthlyEventCap::increment()`.
 * ⚠️ **The four sentences are right about the thing they were each answering
 * and wrong as written** — nothing counts *beacons*, and no figure here can be
 * differenced against `etl_runs.l0_objects` for the reasons above — so what is
 * owed is a narrower sentence rather than a new mechanism. Those files belong
 * to other lanes this wave; the replacement wording is in the commit message.
 */
final class PixelAcceptances
{
    /**
     * How far back an acceptance still counts as evidence about today.
     *
     * ⛔ **A BOUND IS NOT OPTIONAL HERE, AND THE REASON IS THE READER ON THE
     * OTHER SIDE OF THE COMPARISON.** `pixel_monthly_usage` is pruned by
     * nothing, and the derived layer this is compared against expires at
     * {@see WarehouseRetention::L1_RETENTION_DAYS} —
     * §18's four hundred days. Asking *"did this account ever have anything
     * admitted"* would therefore report a tenant whose only traffic aged out of
     * the derived layer as a tenant whose visits we lost, which is the same
     * false certainty in the opposite direction. **Two calendar months is
     * comfortably inside four hundred days at every month length**, so an
     * acceptance inside this window cannot be explained by the horizon.
     */
    public const int MONTHS_CONSIDERED = 2;

    /**
     * Did anything of this tenant's get past the collector's gates inside the
     * window.
     *
     * ⛔ **A BOOLEAN, AND THE REFUSAL TO RETURN A NUMBER IS THE POINT.** The
     * screen that asks this needs one fact — *is silence here a fact about
     * their website or a fact about us* — and a count would be read as
     * analytics by the person it is shown to. It would also be the wrong
     * number: it is events rather than visits, it is taken upstream of two
     * further refusals, and it would sit on a page whose whole subject is not
     * claiming more than the schema supports. [[\App\Services\Warehouse\PixelArrivals]]
     * refuses to grow a count for the same reason and this is the second half
     * of that argument.
     *
     * ⚠️ **`events_total`, NEVER `events_dropped` AND NEVER THE ROW'S
     * EXISTENCE.** A batch refused *at* the cap still writes a row and still
     * stamps `updated_at` — see {@see MonthlyEventCap::admit()}'s refused arm —
     * so `exists()` on the row, or a freshness read of `updated_at`, would call
     * a capped tenant's deliberately-dropped traffic an acceptance. Only
     * `events_total` moves on the arm that goes on to dispatch.
     *
     * ⛔ **NO `Tenancy::idOrFail()` HERE, ON 7804's RULING** — the same
     * question, one table over. `PixelMonthlyUsage` carries the tenancy trait
     * and `TenantScope::apply()` calls `idOrFail()` itself, so a guard on this
     * line would throw the identical `TenantNotResolved` one frame earlier and
     * **survive its own mutation**, which is 398's shape. The fail-closed
     * property is real and it is the scope's; what a test must guard is
     * somebody reaching for `withoutGlobalScope()` on a reader over other
     * people's traffic.
     *
     * ⚠️ **THE MONTH BOUNDARY IS UTC**, because {@see MonthlyEventCap::admit()}
     * keys the row on `$at->utc()->startOfMonth()`. Comparing against a local
     * month would miss a row on the first of the month for as long as the two
     * disagree.
     */
    public function acceptedRecently(?CarbonImmutable $at = null): bool
    {
        $at ??= CarbonImmutable::now();

        $since = $at->utc()
            ->startOfMonth()
            ->subMonths(self::MONTHS_CONSIDERED - 1);

        return PixelMonthlyUsage::query()
            ->where('month', '>=', $since->toDateString())
            ->where('events_total', '>', 0)
            ->exists();
    }
}
