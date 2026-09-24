<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When this business last received the monthly owner digest.
 *
 * ⛔ **A CURSOR, NEVER A PREFERENCE — SO IT IS UNGUARDED FROM THE TENANT'S OWN
 * SIDE, `Business`' OWN `$guarded` PATTERN.** Nothing on any owner-facing screen
 * writes this column; it exists only so the command can ask "has it been at least
 * a month" without a second table.
 *
 * ⚠️ **NULLABLE, AND NULL MEANS "NEVER SENT" RATHER THAN A SENTINEL DATE.** A
 * business with no digest yet is due immediately once it clears the
 * exclusion `MonthlyDigest::eligible()` applies.
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
            $table->timestamp('owner_monthly_digest_sent_at')->nullable()->after('owner_digest_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn('owner_monthly_digest_sent_at');
        });
    }
};
