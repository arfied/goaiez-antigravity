<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The evidence behind an automatic platform halt — the half of 2102 that was
 * never built, and 2119's brief.
 *
 * ## What this table is NOT, said first because it is the whole design
 *
 * ⛔ **THIS IS NOT THE SWITCH AND IS NEVER READ TO DECIDE WHETHER TO SEND.**
 * The switch is `messaging.global_halt` in the defaults registry, which
 * `SendingGuard` and `ComplianceReplies` already read, which the admin settings
 * screen already writes with an actor, and which already carries an append-only
 * change history. **That stays exactly as it is.**
 *
 * 2186 is the reason the separation is not merely tidy: *"what must not happen
 * is a second complaint counter beside this one, because then the trip reads one
 * of them."* A second table answering *"is the platform halted"* is the same
 * defect one level up — the day the row and the registry key disagree, every
 * screen is right about a different thing. So this table answers a question
 * nothing on the send path asks: **why did it trip, and against what numbers.**
 *
 * ⚠️ **THE MEASUREMENT IS ARGUABLE AND THE STOP MUST NOT BE.** An incident
 * review disputes a rate; it never disputes whether a switch was thrown. Keeping
 * the rate here and the switch there is what lets somebody argue with the first
 * without touching the second.
 *
 * ## Automatic trips only
 *
 * A halt an operator throws by hand is already recorded where the registry
 * records every setting change: the key, the actor, the before and the after. It
 * does not appear here, and that asymmetry is deliberate rather than an
 * omission — this table exists because **a rate is a thing a registry history
 * cannot hold**, and an operator's decision has no rate to hold.
 *
 * ## No tenant, and therefore no RLS
 *
 * A platform halt belongs to no business, exactly as `compliance_suppressions`
 * and `state_messaging_rules` belong to none (483): a rate measured across every
 * tenant at once is a fact about the platform's own carrier reputation, which is
 * precisely what 2101 says the platform carries and the tenants do not.
 * `business_id` would have to be nullable and the policy would have to admit
 * NULL — and a policy admitting NULL admits every row, which is how a tenant
 * table quietly stops being one (the `sending_pauses` migration makes the same
 * argument from the other side).
 *
 * ⚠️ **WHAT REPLACES THE SCOPE.** Nothing leakable is stored: five integers, a
 * count of tenants, and a timestamp. **No phone number, no customer, no tenant
 * name, and deliberately not even a business id** — naming the worst tenant here
 * would put one tenant's reputation in a platform-wide record that support reads
 * routinely, and the aggregate is the thing being contained anyway (2119(b)).
 * The only writer is `WatchPlatformComplaintRate`, which is a console command
 * behind shell access, and a lint pins that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_halt_incidents', function (Blueprint $table): void {
            $table->id();

            // The sample, as it stood at the moment of the trip. ⚠️ **A
            // snapshot, never recomputed** — by the time anybody reads this the
            // window has rolled and the numbers that caused it are gone. This is
            // `sending_pauses.observed_rate_bp`'s reasoning, with the arithmetic
            // kept rather than only its result, because the first question after
            // an automatic platform halt is whether the rate was real and that
            // cannot be answered from the rate alone.
            $table->unsignedBigInteger('delivered_in_window');
            $table->unsignedBigInteger('complaints_in_window');

            // Basis points. 200 is 2%. Integers for the reason money is integer
            // cents: this is compared against a configured threshold, and a
            // float that survives a JSON round trip as 0.0199999 compares wrong
            // against itself.
            $table->unsignedInteger('rate_basis_points');

            // The threshold as it stood, stored beside the rate it was compared
            // against. ⚠️ **Without this the row cannot be read six months
            // later**: somebody raises the number after an alert (511's failure,
            // which the manifest warns about by name for the per-tenant twin),
            // and every historic incident silently starts reading as if it had
            // been measured against the new one.
            $table->unsignedInteger('threshold_basis_points');

            // How many hours the window covered, for the same reason.
            $table->unsignedSmallInteger('window_hours');

            // How many tenants contributed to the aggregate. Not a tenant list —
            // see the class docblock. It is here because an aggregate over three
            // tenants and an aggregate over three hundred are different evidence
            // for the identical rate.
            $table->unsignedInteger('tenant_count');

            $table->timestamp('tripped_at');

            $table->timestamps();

            // The admin incident list reads newest first.
            $table->index('tripped_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_halt_incidents');
    }
};
