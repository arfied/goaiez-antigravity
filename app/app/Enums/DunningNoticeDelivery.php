<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Billing\DunningNotices;
use App\Services\Mail\PlatformMailer;

/**
 * What this application actually knows about a dunning notice's email — 10990.
 *
 * ⛔ **THIS REPLACES A BOOLEAN CALLED `emailed` THAT WAS TRUE FOR A MESSAGE
 * NOBODY HAD ACCEPTED.** {@see DunningNotices} writes one entry per notice to
 * the **append-only** audit log, and its own docblock says why that entry
 * exists: *"the append-only record of 'we took the product away and could not
 * tell them' is exactly the fact a billing dispute turns on."* The value it
 * carried was set immediately after {@see PlatformMailer::send()}, which is a
 * `DeliverPlatformMail::dispatch()` inside a `catch (Throwable)` that logs and
 * returns `void` (9500) — so a message never handed to the queue at all was
 * recorded as `emailed: true`. ⛔ **An append-only record cannot be corrected
 * later; that is what append-only means.** The repair therefore has to be a
 * value that is true when it is written rather than a later amendment.
 *
 * ⚠️ **NONE OF THESE CASES MEANS DELIVERED, AND THE NAMES ARE CHOSEN SO THAT
 * NOBODY CAN READ ONE THAT WAY.** `PlatformMailer::canDeliver()`'s own docblock
 * — *"TRUE HERE IS NOT A DELIVERY … the honest reading is 'not knowably
 * undeliverable'"* — governs the widest thing this class can claim. The
 * `operator_alerts` board says the same about its own strongest state:
 * *"accepted is not the same as arriving."* **This one cannot even claim
 * accepted** (see {@see self::Queued}).
 *
 * ⛔ **THE KEY IS `mail` AND NOT `emailed`, DELIBERATELY.** Rows written before
 * 2026-08-28 carry `emailed` as a boolean and cannot be rewritten, so reusing
 * the name would make one key mean a boolean on old rows and a string on new
 * ones — in the one record a dispute is argued from. A new key leaves every
 * historical row saying exactly what it always said, and this enum is where a
 * reader finds out what that was.
 */
enum DunningNoticeDelivery: string
{
    /**
     * Handed to the mail queue, and nothing beyond that is known.
     *
     * ⛔ **THIS IS THE STATE THE OLD BOOLEAN CALLED `true`, AND IT IS WEAKER
     * THAN "ACCEPTED BY THE TRANSPORT".** {@see PlatformMailer::send()} pushes
     * `App\Jobs\DeliverPlatformMail` and swallows a dispatch failure, so even
     * this word is generous: on a `jobs` table that cannot be written, or a
     * Redis refusing a connection, no job ever existed and this value is still
     * what gets written. **What it honestly asserts is that this application
     * tried and did not refuse.**
     *
     * ⚠️ **`deliverNow()` WOULD MAKE A STRONGER CLAIM POSSIBLE AND IS REFUSED
     * HERE FOR A REASON THAT IS NOT LATENCY** (10991). One of this notice's two
     * entry points is `AuthorizeNetWebhooks::process()`, which wraps
     * `Dunning::open()` in an outer transaction — so a synchronous send would
     * put a real email in front of a customer from inside a transaction that
     * may still roll back, where a queued job's `jobs` row rolls back with it.
     * **A rollback that un-sends is worth more here than a stronger word in the
     * audit log.**
     *
     * ⚠️ **THE CHAIN WAS WALKED RATHER THAN TAKEN FROM A DOCBLOCK** (11005):
     * `Http\Controllers\Billing\AuthorizeNetWebhookController:82` →
     * `AuthorizeNetWebhooks::process()`, whose body **is** a `DB::transaction()`
     * → `openDunning()` → `Dunning::open()` → here. ⛔ **AND THE ROLLBACK HALF
     * HOLDS ON THE `database` QUEUE AND NOT ON REDIS.** `CLAUDE.md` §Tech stack
     * records that every deployment runs queue, cache and session on
     * `database` and that Horizon has never been started, so the `jobs` row is
     * in the same transaction today. **On the Redis stack that is a design
     * rather than a running configuration, the push would survive a rollback**
     * — which makes this a fact about the deployment that exists rather than a
     * property of the code, and it is written down instead of assumed.
     */
    case Queued = 'queued';

    /**
     * There was nobody to write to.
     *
     * ⚠️ **AN EMPTY ADDRESS RATHER THAN A MISSING OWNER.**
     * `businesses.owner_user_id` is NOT NULL, so the missing-owner half of the
     * guard can only fire on a row the schema forbids; the empty-string half
     * can. Either way the dunning schedule still runs, because the vendor's
     * termination clock does not care that we cannot reach anybody.
     */
    case NoAddress = 'no_address';

    /**
     * The mail system is not configured to send at all.
     *
     * ⚠️ **A PLATFORM FACT AND NEVER A FACT ABOUT THIS TENANT.**
     * {@see PlatformMailer::canDeliver()} answers about the transport, the from
     * address and decision 30's domain rule — identical for every business on
     * the install — so a run of these in the audit log is one misconfiguration
     * and not a run of unreachable customers.
     */
    case TransportUnavailable = 'transport_unavailable';

    /**
     * What an operator reading a billing dispute should take from this row.
     *
     * ⚠️ **WRITTEN FOR THE PERSON ARGUING THE DISPUTE RATHER THAN FOR A
     * SCREEN.** There is no Ops surface over dunning audit metadata today; this
     * exists so that the sentence lives beside the value rather than being
     * re-derived by whoever opens the row, which is how `emailed: true` came to
     * be read as *"we told them"* in the first place.
     */
    public function auditSummary(): string
    {
        return match ($this) {
            self::Queued => 'Handed to the mail queue. Not a delivery, and not even an acceptance by the transport.',
            self::NoAddress => 'There was no address on the account, so nothing was attempted.',
            self::TransportUnavailable => 'This installation could not send mail at all when the notice was due.',
        };
    }
}
