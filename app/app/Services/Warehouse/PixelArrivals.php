<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Models\L1Event;
use Carbon\CarbonImmutable;

/**
 * When this tenant's pixel last got something *through* — the acceptance signal
 * four artefacts said this application did not have (7980–7983).
 *
 * ---------------------------------------------------------------------------
 * ⛔ THE DEFECT THIS CLASS EXISTS FOR: A PARAGRAPH STANDING WHERE THE MECHANISM
 * ALREADY WAS
 * ---------------------------------------------------------------------------
 * `Account\PixelInstall`'s docblock said, of a "connected" indicator, that
 * *"`pixel_keys` carries no `last_seen_at` and nothing in this application
 * writes one — the collector archives to object storage on a queued job, **never
 * to a row this screen could query**, and the only thing that turns L0 into
 * anything queryable (`warehouse:replay`) is a manual command with no
 * schedule."* [[\App\Enums\PixelCollectionState]] said the same in stronger
 * words — *"a green tick would be a claim nothing in this application could ever
 * have made"* — and 7808(a) recorded it as *"there is still no acceptance signal
 * anywhere in the schema"*.
 *
 * ⛔ **[[\App\Jobs\ArchivePixelBatchJob]] LOADS `L1Loader` INLINE, IN THE SAME
 * JOB, IMMEDIATELY AFTER THE L0 STORE.** The rows are per tenant
 * (`business_id NOT NULL`, foreign key), per event, `ENABLE`+`FORCE` row-level
 * secured on `app.business_id`, and carry an index on
 * `(business_id, received_at, event_id)` — the index for exactly this question.
 * `git log -S"L1Loader" -- app/Jobs/ArchivePixelBatchJob.php` returns one
 * commit, `f3b42066`, twelve hours **earlier** than `b9e65304`, which added the
 * screen that says it is impossible. **The mechanism was there first and the
 * paragraph is what stopped the next reader looking** — `CLAUDE.md` 314–316,
 * which is the same failure `PixelCollections` was built to end one wave ago
 * and repeated one file away from it.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ WHY IT LIVES HERE AND NOT IN `app/Services/Pixel/`
 * ---------------------------------------------------------------------------
 * `WarehouseTest`'s *"nothing outside the warehouse writes to a derived layer"*
 * lint fails the build on any file under `app/` naming a derived table or model
 * class outside this directory, and its message says the rest: *"Reads belong
 * behind a service and writes belong nowhere."* [[PixelSightings]] is the same
 * shape and made the same move for the same reason (5543) — its caller is in
 * `app/Services/Actuation/`, mine is in `app/Services/Pixel/`, and neither may
 * name the table.
 *
 * ⚠️ **READ-ONLY, AS A PROPERTY OF THE CLASS RATHER THAN A HABIT** —
 * [[PixelSightings]]' sentence, and it is worth repeating because the tempting
 * next step is exactly the one refused below. A derived layer is truncated and
 * rebuilt from L0, so anything written by hand is destroyed by the next replay,
 * silently, because the replay reports success.
 *
 * ---------------------------------------------------------------------------
 * ⛔ WHAT AN ANSWER FROM HERE PROVES, AND THE FIVE THINGS IT DOES NOT (352, 397)
 * ---------------------------------------------------------------------------
 * **The positive is strong.** A row exists only if a batch presented this
 * tenant's public key, from an origin their own account lists, past the rule 24
 * gate, the monthly cap, the GPC check and the form-value check, was archived to
 * L0 and derived. Nothing else in this application can put one there. So a
 * non-null answer means *we accepted and kept your visitors' events*, which is
 * the sentence the install screen has never been able to say.
 *
 * **The negative is weak, in FIVE separate ways, and every one of them is a
 * reason a screen may not render silence as failure.**
 *
 * ⛔ **IT SAID FOUR UNTIL 2026-08-26, AND THE ONE IT WAS MISSING IS THE ONE
 * THAT ACTUALLY HAPPENED — BOTH READINGS KEPT AND DATED** (9900, 9904). **A
 * docblock that enumerates its own weaknesses and omits one is worse than a
 * docblock that enumerates none**, because the list is read as complete: this
 * one was quoted as the argument for a sentence on a tenant's screen, and the
 * missing entry is the reason that sentence was false. It is item **5**.
 *
 *  1. ⚠️ **THE WRITE IS QUEUED.** [[\App\Services\Pixel\PixelCollector]] answers
 *     `204` and dispatches; L1 is written when the worker gets to it. So the
 *     absence of an arrival in the last minute is queue latency, and on a box
 *     with a stopped worker it is a stopped worker.
 *  2. ⚠️ **A BATCH THAT DERIVES ZERO ROWS IS NOT AN ERROR.** `L1Derivation::
 *     rows()` returns `[]` for a payload it cannot decode, for a non-array
 *     `events` key, and for every event with no `event_id` — by design, because
 *     a malformed byte in an append-only archive must not fail every future
 *     replay of its range. Such a batch is *accepted*, archived, and invisible
 *     here.
 *  3. ⚠️ **THE TABLE HAS A HORIZON AND THIS READER IS BEHIND IT.**
 *     {@see WarehouseRetention::L1_RETENTION_DAYS} — §18's *"L1 400 days"* — so
 *     a tenant whose only traffic is older than that reads as a tenant who never
 *     sent any, and this answer walks forward as the sweep runs. It is *"when
 *     did we last hear from you, within the last 400 days"* and never *"have you
 *     ever been seen"*. L0 still holds the events and `warehouse:replay` still
 *     rebuilds them, which is why this is a limit on the reader rather than a
 *     loss.
 *  4. ⚠️ **A REPLAY CAN MOVE IT.** L1 is derived and disposable; a range emptied
 *     and not yet rebuilt answers as silence for as long as that is true.
 *  5. ⛔ **THE ARCHIVE CAN BE REFUSING WRITES, AND THEN THIS READS ZERO WHILE
 *     THE TENANT'S WEBSITE IS BUSY.** [[\App\Jobs\ArchivePixelBatchJob]] stores
 *     the L0 object **first** and derives **second**, in one method, so a disk
 *     that throws leaves no row here either (9844) — this reader cannot tell
 *     that apart from a website nobody visited, and **the difference is the
 *     whole of what an owner came to the install screen to find out.**
 *     ⛔ **This is the measured production case rather than the hypothetical
 *     one**: `Storage::disk('s3')` could not be built on any deployment between
 *     2026-08-18 and 2026-08-25 (9408, 9421), so for a week every accepted
 *     beacon spent three attempts and landed in `failed_jobs`, and this reader
 *     answered `null` throughout. ✅ **What tells them apart is
 *     `App\Services\Pixel\PixelAcceptances`**, reading the admission counter
 *     the collector writes before the queue ever sees the batch — it is
 *     deliberately not read from here, because this class's subject is the
 *     derived layer and a reader that answered both questions would be the one
 *     place a screen could stop asking either.
 *
 * ⚠️ **BOTS ARE NOT FILTERED, AND FILTERING WOULD MAKE THE ANSWER WORSE** —
 * [[PixelSightings]]' reasoning, arriving at a screen this time. `is_bot` is a
 * threshold on a score, so excluding those rows would convert a
 * misclassification into *"nothing is arriving"* on the one screen a person
 * opens to find out whether their install worked. A crawler executing our
 * bundle on their website is evidence the line is installed and reaching us,
 * which is the whole claim.
 *
 * ⛔ **NOTHING HERE MAY GROW A `COUNT`, A HOST LIST OR A DATE HISTOGRAM WITHOUT
 * A NEW ARGUMENT.** The screen this serves needs one fact — *did anything get
 * through, and when* — and every additional column is either a number a tenant
 * will read as their analytics (which is `28` §4's job and a different screen)
 * or a set of strings a stranger's browser wrote.
 */
final class PixelArrivals
{
    /**
     * When this tenant's pixel last had something accepted and kept.
     *
     * ⚠️ **`received_at`, NEVER `occurred_at`.** The question is when something
     * reached *us*, which is the claim a screen may make; `occurred_at` is the
     * browser's own clock, falls back to the receipt time when the client sends
     * a bad one (`L1Derivation`), and is not the column the index leads with.
     * {@see WarehouseRetention} expires rows on `received_at` for the same
     * reason.
     *
     * ⚠️ **AN AGGREGATE RATHER THAN AN ORDERED `first()`, AND THAT IS NOT
     * MERELY TASTE.** `ConventionsTest` fails the build on a descending sort
     * over anything but `id` — `received_at` is `NOT NULL` here so the hazard it
     * guards does not apply, but writing `orderByRaw('… DESC NULLS LAST')` to
     * say so would be a raw clause bought for nothing. `max()` reads the same
     * index backwards and needs no exception.
     *
     * ⛔ **THERE IS NO `Tenancy::idOrFail()` ON THIS METHOD AND ITS ABSENCE IS
     * DELIBERATE — 7804, WHICH IS THIS CODEBASE'S OWN RULING ON EXACTLY THIS
     * LINE.** `L1Event` carries the tenancy trait, `TenantScope::apply()` calls
     * `Tenancy::idOrFail()` itself, and Eloquent applies scopes to a passthru
     * aggregate — so a guard here would throw the same `TenantNotResolved` one
     * frame earlier and **survive its own mutation**, which is 398's shape and
     * is how `PixelCollections::status()` came to carry one with a docblock
     * arguing for it. ⚠️ **The fail-closed property is real and is the scope's**,
     * and the test naming it stays: it reddens the day somebody reaches for
     * `withoutGlobalScope()`, which is the change that would actually matter on
     * a reader over other people's visitors.
     *
     * ⚠️ **PARSED AS UTC EXPLICITLY.** The column is `timestamp(3)` with no
     * zone, holding what `L0Batch::lines()` wrote after `->utc()`. This
     * application's `app.timezone` is `UTC` so the two agree today; naming it
     * means they still agree on the day it is not.
     */
    public function lastArrivedAt(): ?CarbonImmutable
    {
        $max = L1Event::query()->max('received_at');

        if (! is_string($max) || $max === '') {
            return null;
        }

        return CarbonImmutable::parse($max, 'UTC');
    }
}
