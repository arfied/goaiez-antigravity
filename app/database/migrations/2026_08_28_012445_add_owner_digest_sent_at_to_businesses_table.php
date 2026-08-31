<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When this business last received the weekly owner digest — wave 39 lane C.
 *
 * ⛔ **A CURSOR, NEVER A PREFERENCE — SO IT IS UNGUARDED FROM THE TENANT'S OWN
 * SIDE, `Business`' OWN `$guarded` PATTERN.** Nothing on any owner-facing screen
 * writes this column; it exists only so
 * `App\Console\Commands\SendOwnerWeeklyDigests` can ask "has it been at least
 * seven days" without a second table. `paused_at` and `suspended_at` sit beside
 * it on the same model's guard list for the same reason: a tenant's own request
 * payload must never be able to move a column that decides what they receive
 * next, even though — unlike those two — getting this one wrong costs an email,
 * not a compliance boundary.
 *
 * ⚠️ **NULLABLE, AND NULL MEANS "NEVER SENT" RATHER THAN A SENTINEL DATE.** A
 * business with no digest yet is due immediately once it clears the first-week
 * exclusion `OwnerDigest::eligible()` applies — `AdvanceFirstWeekPathJob`'s own
 * ladder already owns the first seven days of owner communication, and this
 * column's null state is what lets the digest command tell "never sent" from
 * "sent a long time ago" without a second column.
 *
 * ⚠️ **NOT BACKFILLED, ON THIS FILE'S OWN RULE.** A backfill of a tenant-owned
 * column cannot be an `UPDATE` in a migration — it establishes no tenant, and
 * `businesses` is FORCE row-level security — so every existing row reads null,
 * which is the correct "never sent" answer for a column that did not exist
 * until this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->timestamp('owner_digest_sent_at')->nullable()->after('paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn('owner_digest_sent_at');
        });
    }
};
