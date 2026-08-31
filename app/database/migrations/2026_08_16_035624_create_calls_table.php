<?php

declare(strict_types=1);

use App\Enums\CallOutcome;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One inbound call to a tenant's number — T176 P2, the voice half of R7.
 *
 * ⛔ **THERE IS NO `direction` COLUMN AND THERE MAY NEVER BE ONE.** `29` §2.3
 * rule 13 was **overridden by an owner ruling on 2026-08-25** (9363), and the
 * absence is held by this schema together with `VoiceTest`'s project-wide
 * call-direction census — which carries its own case-insensitivity proof —
 * rather than by the rule. `App\Services\Voice\InboundCall`
 * refuses the same field for the same reason — *"a `direction` property would be
 * the first line of outbound calling, written by somebody who thought they were
 * being general"* — and a nullable column is a worse version of that, because it
 * would also be a place to record a call this platform must never place.
 *
 * ⛔ **AND THERE IS NO `recording_announced` COLUMN** (2104). The announcement is
 * played on every recorded call in every state, unconditionally, from the
 * pre-rendered greeting. A boolean recording that it happened *is a boolean that
 * can be false*, and a nullable one somebody has to set is exactly how "in every
 * state" becomes "in most states". `App\Services\Voice\VoiceGreeting` is where it
 * is enforced — in the object that decides what a caller hears, which cannot be
 * built without it.
 *
 * ## The number is plaintext, and that is the codebase's own posture
 *
 * ⚠️ **`from_e164` IS A MEMBER OF THE PUBLIC'S MOBILE NUMBER AND IT IS NOT
 * HASHED**, which is the opposite of `opt_outs.value_hash` and
 * `inbound_messages.value_hash` — and the difference is the tenant boundary.
 * Those two tables are **platform-scoped**: they are read with no tenant
 * resolved, so a hash is the only thing standing between one tenant's register
 * and another's. This table is tenant-owned, carries `BelongsToTenant`, and sits
 * under `ENABLE`+`FORCE` row-level security, exactly as `customers.phone` does —
 * and `customers.phone` is stored in plaintext for the same reason this is: the
 * owner has to be able to ring back the person who rang them. A hash here would
 * make the feature's whole output unreadable to the only person entitled to it.
 *
 * ## Idempotency
 *
 * ⚠️ **`provider_call_id` IS UNIQUE ACROSS THE PLATFORM, NOT PER TENANT.** Voice
 * webhooks redeliver, and the vendor's call id is what a redelivery is
 * recognised by. A `(business_id, provider_call_id)` unique would let the same
 * call be written twice under two tenants if the number-to-tenant resolution
 * ever disagreed with itself, which is the failure this constraint exists to
 * make impossible rather than merely unlikely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Null is ordinary — the call arrived on a number that names no
            // location. `AutopilotJob` takes a nullable location for the same
            // reason: some work cannot name one.
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            // ⚠️ NULL IS THE COMMON CASE AND IS NOT AN ERROR. A first-time
            // caller has no contact row; `InboundCall`'s docblock says so, and
            // nothing on this path may create one — see `MissedCallTextBack`.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('provider_call_id')->unique();

            $table->string('from_e164');
            $table->string('to_e164');

            // Cast to CallOutcome. A string, never a database enum (CLAUDE.md).
            $table->string('outcome')->default(CallOutcome::InProgress->value);

            // ⚠️ **THE VENDOR'S OWN `state`, KEPT RAW BESIDE OUR READING OF IT.**
            // `CallOutcome::fromProviderState()` is one place and one mapping;
            // this column is what lets somebody check that mapping against a real
            // call months later without a vendor log. It is a state name, never a
            // credential and never a person.
            $table->string('provider_state')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('ring_seconds')->nullable();

            $table->timestamps();

            // The owner's own screen reads their calls newest first.
            $table->index(['business_id', 'started_at']);
        });

        $outcomes = collect(CallOutcome::cases())
            ->map(fn (CallOutcome $outcome): string => "'{$outcome->value}'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE calls
                ADD CONSTRAINT calls_outcome_is_known
                CHECK (outcome IN ({$outcomes}))
        SQL);

        DB::statement('ALTER TABLE calls ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE calls FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON calls
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
