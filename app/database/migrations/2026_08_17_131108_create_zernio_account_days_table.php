<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per connected Zernio account per day — our copy of Zernio's own meter.
 *
 * ⚠️ **THE UNIT IS AN ACCOUNT-DAY, NOT AN API CALL, AND THAT IS THE WHOLE
 * REASON THIS TABLE DOES NOT LOOK LIKE `places_api_calls`.** Zernio's published
 * pricing bills *connected social accounts*, graduated, and says of API calls:
 * *"Every account — free or paid — includes everything: full API access,
 * unlimited posts, all 14 platforms, Analytics, Inbox, and Ads. There are no
 * profile limits, no post caps, no add-ons, and no per-feature pricing."*
 * (docs.zernio.com/pricing, read 2026-08-17.) A per-call ledger here would meter
 * a quantity whose price is zero and produce a permanently-zero spend rate —
 * `sending_health_windows` with a Google logo on it.
 *
 * Their billing page defines the meter this table copies, verbatim: *"Every day,
 * our system records which accounts are connected and reports them to the
 * billing engine, one event per active account per day."* Writing the same row
 * on the same schedule is what makes our number reconcilable against their
 * invoice rather than merely plausible beside it.
 *
 * NOT TENANT-OWNED, and it cannot be, for two separate reasons. The first is
 * `places_api_calls`': a ledger the tenant scope hides is a ledger the
 * platform-wide budget query cannot sum. The second is sharper, and it is why
 * `business_id` is an ordinary integer with **no foreign key**: a business may
 * be erased under a statutory deletion while **Zernio keeps billing us for the
 * account it still has connected**. If this row cascaded away with the tenant,
 * the one record proving we are paying for a stranger's listing would be
 * destroyed by the very event that created the leak.
 *
 * ⚠️ **`account_ref` IS A THIRD PARTY'S OPAQUE ID AND CARRIES NO PERSONAL
 * DATA** — the same value `gbp_account_bindings` is keyed by, and
 * `SUBPROCESSOR-INVENTORY.md` §2 already describes what we exchange with Zernio
 * as our key and an opaque reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zernio_account_days', function (Blueprint $table): void {
            $table->id();

            // Zernio's `accountId`. Their glossary: "Account-day — the billing
            // meter for connected accounts: one unit per account per day it
            // stays connected."
            $table->string('account_ref', 128);

            // The day this account was observed connected. A date and not a
            // timestamp: the unit is a day, and a timestamp would invite a
            // second row for the same day from a re-run.
            $table->date('on_day');

            // Who it was connected for at the time of the reading. Nullable and
            // unconstrained: a null means the binding had gone while Zernio's
            // account had not — the orphan this ledger exists to make visible.
            $table->unsignedBigInteger('business_id')->nullable();

            $table->timestamp('recorded_at');

            // Idempotency, in the schema rather than in the command. The daily
            // sweep may run twice — a retried cron, a manual invocation, an
            // operator checking the figure — and an account-day counted twice
            // overstates the bill by exactly the amount that would make somebody
            // raise a ceiling that was never breached.
            $table->unique(['account_ref', 'on_day']);

            // "What have we accrued this month?" — the aggregate this table
            // exists to serve, and a range scan over one column.
            $table->index(['on_day', 'business_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zernio_account_days');
    }
};
