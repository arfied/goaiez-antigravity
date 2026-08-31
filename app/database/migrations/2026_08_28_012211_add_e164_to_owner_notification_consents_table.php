<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The evidence row could not name the number it was evidence for (wave 39
 * lane A, decision block 10660).
 *
 * ⛔ **THE DEFECT THIS CLOSES**: `owner_notification_consents` recorded who was
 * shown the disclosure, when, from what page, and on what device — every field
 * `29` §2 rule 7 asks for except the one a carrier complaint is actually about.
 * A carrier forwarding *"this number never agreed to be texted"* could not be
 * answered from this table alone; the only number in the whole owner channel
 * lived on `owner_notify_numbers`, the CURRENT-STATE row, which
 * `OwnerConsentService::capture()`'s own `updateOrCreate` overwrites on every
 * fresh capture — so a corrected mistyped digit left no record of what the
 * *previous* capture had agreed to.
 *
 * ⚠️ **PLAINTEXT, DELIBERATELY, NOT A HASH** — the whole defect is that nothing
 * could answer "which number" to a human, and a hash cannot answer that either.
 * Safe here in a way it is not on `owner_notify_numbers`: this table is
 * `BelongsToTenant`, RLS `ENABLE`+`FORCE`d with a real `business_id` predicate
 * (the creating migration), not `USING (true)` — so plaintext here is bounded by
 * the same tenant isolation every other tenant-owned column already relies on,
 * where `owner_notify_numbers.e164` has no such predicate to lean on at all.
 *
 * ⚠️ **NULLABLE, AND THE ROWS THAT PREDATE IT STAY NULL.** `CLAUDE.md`: a
 * backfill of a tenant-owned column cannot be an `UPDATE` in a migration — no
 * tenant is established here, so an `UPDATE` over a FORCE-RLS table matches
 * zero rows and reports success, which is worse than doing nothing and saying
 * so. Deriving it from `owner_notify_numbers.business_id` in DDL is also not
 * available — a generated column cannot join another table. Wave 38 shipped and
 * was deployed the same day this is written, so the honest expectation is that
 * few or no real consent rows predate this column; if any do, their number is
 * unrecoverable from this table and recoverable only by whatever the CURRENT
 * `owner_notify_numbers.e164` happens to hold for that business — which may no
 * longer be the number that particular row was evidence for. That gap is
 * accepted rather than hidden behind a silent backfill that would have written
 * nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_notification_consents', function (Blueprint $table): void {
            $table->string('e164')->nullable()->after('method');
        });
    }

    public function down(): void
    {
        Schema::table('owner_notification_consents', function (Blueprint $table): void {
            $table->dropColumn('e164');
        });
    }
};
