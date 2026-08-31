<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every duration-billed second of inbound voice, and whose number it arrived on.
 *
 * ## Why this table exists at all — decision 4686
 *
 * The 3297 enumeration found inbound voice **billed by duration and counted
 * nowhere**: no meter, no budget, no ceiling, no debit. Two things make it the
 * sharpest of the three uncapped paths. A text or an email has a known worst
 * case per event; **a call's cost is unbounded above**, because the caller
 * chooses how long to talk. And it is **the one uncapped path a stranger can
 * trigger** — nobody has to be a tenant, logged in, or consented to make it cost
 * money. They dial a number.
 *
 * ⛔ **THE ROWS THAT MATTER MOST HAVE NO TENANT, WHICH IS WHY THEY COULD NOT
 * LIVE ON `calls`.** `VoiceCalls::record()` answers `UnknownNumber` and writes
 * **nothing** when the called number resolves to no business — and its own
 * docblock says that is *"the common case today"*, because the shared Lane A
 * pool number belongs to no tenant. So the calls nobody is commercially
 * responsible for are exactly the ones invisible to every tenant-scoped counter,
 * and a meter built on `calls` would have been blind to the abuse case it was
 * built for. `business_id` here is nullable and a null is a real answer.
 *
 * NOT TENANT-OWNED, AND IT CANNOT BE — `places_api_calls`' argument, one channel
 * over. A global scope would hide every unattributed row from the query that
 * enforces the ceiling, and row-level security would refuse them outright. **A
 * budget that cannot see its own spend is not a budget.** The tenant half of
 * every predicate is written explicitly in `VoiceSpend`, which is the only file
 * permitted to touch this model.
 *
 * ## Seconds, and deliberately no money column
 *
 * ⛔ **THERE IS NO PRICE ON THE ROW, WHICH IS THE OPPOSITE OF WHAT
 * `places_api_calls` DOES, AND THE REASON IS A VENDOR FACT RATHER THAN A
 * PREFERENCE.** Google publishes a per-SKU list price, so a Places row can carry
 * one honestly. Infobip does not publish a base voice per-minute rate at all:
 * `https://www.infobip.com/voice/pricing`, read 2026-08-17, says *"We display
 * the average price across all supported networks for each country. Per-network
 * pricing is available in Portal"* — the rate is per account, per destination
 * network, and behind a login. `MessageRates` reached the identical conclusion
 * about this vendor's SMS rate and left it unseeded for the identical reason.
 *
 * A `unit_millicents_per_minute` column here could therefore only ever hold a
 * zero, and `MessageCostLedger` already refuses that in as many words: *"a cost
 * of zero records nothing … afterwards 'we do not know' cannot be told apart
 * from 'it was free'"*. **Seconds is the unit the vendor bills in and the one an
 * operator can reason about without a rate**, so seconds is what is stored and
 * minutes is what the ceilings are denominated in.
 *
 * ⚠️ **TWO ADD-ON RATES *ARE* PUBLISHED AND ARE STILL NOT SEEDED** — voice and
 * video recording at **€0.0021/min** and speech transcription at **€0.0402/min**
 * (same page, same date). They are quoted in `VoiceUsageKind` because the ratio
 * between them is load-bearing for which spend is worth gating, and they are not
 * written into any column because they are **euros**, and this platform's cost
 * book has one currency read from one registry key. Two sources for one fact is
 * 3894's defect, which rendered a wrong total perfectly.
 *
 * ## One row per call per kind, for ever
 *
 * ⚠️ **VOICE EVENTS ARRIVE TWICE AND OUT OF ORDER** — `VoiceIngestOutcome::Duplicate`
 * exists for that, and `IngestVoiceEventJob` retries. A meter that counted a
 * redelivered webhook twice would report a busy afternoon as an incident and
 * would trip a ceiling nobody crossed, so the unique index is the idempotency
 * and `VoiceSpend::record()` inserts through it rather than checking first
 * (decision 350: check-then-insert holds only sequentially).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_usage_events', function (Blueprint $table): void {
            $table->id();

            // Cast to App\Enums\VoiceUsageKind. A string column, never a
            // database enum — the standing rule, and the set is this vendor's
            // billing model to change.
            $table->string('kind', 32);

            // The vendor's opaque call handle. Deliberately the only identifier
            // on the row: a caller's mobile number would be personal data in a
            // table with no row-level security over it, which is the distinction
            // 4419 draws between `calls.from_e164` (protected by the boundary)
            // and the platform registers that hash instead.
            $table->string('provider_call_id', 128);

            // What the vendor is billing us for, in seconds. Unsigned: negative
            // time is not a correction, it is a bug, and a signed column would
            // let one silently cancel real usage out of the ceiling.
            $table->unsignedInteger('billable_seconds');

            // Null when the called number resolves to no business — the common
            // case, and the one this table exists to make visible. No foreign
            // key, on `places_api_calls`' reasoning: a business may be erased
            // under a data request while the aggregate spend must survive.
            $table->unsignedBigInteger('business_id')->nullable();

            // When the vendor billed it, not when we heard about it. A webhook
            // redelivered the next morning must not move yesterday's minutes
            // into today's ceiling.
            $table->timestamp('occurred_at');

            $table->timestamp('created_at')->nullable();

            // The idempotency. See the docblock — this is what makes a
            // redelivered event a no-op rather than a second charge.
            $table->unique(['provider_call_id', 'kind']);

            // "How many minutes has the platform taken today, of this kind?"
            $table->index(['occurred_at', 'kind']);

            // "…and how many of them were this tenant's?" Nulls sort together in
            // this index, which is also the unattributed-pool question.
            $table->index(['business_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_usage_events');
    }
};
