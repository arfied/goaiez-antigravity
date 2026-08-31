<?php

declare(strict_types=1);

use App\Services\Gbp\GbpConnections;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every attempt to end a Google grant a deleted tenant left behind — the
 * visibility half of decision 4888(a).
 *
 * `gbp_account_bindings.revocation_owed_at` (4880) is a stamp, not a log: it
 * says a grant is owed, and it says nothing about how many times ending it has
 * been tried or why the last try failed. 4888 named that gap directly —
 * *"nothing surfaces an outstanding grant on a screen … an operator who does
 * not read cron output learns nothing"* — and a screen needs a history to
 * render, not a single mutable timestamp.
 *
 * `credential_changes` is the template rather than `audit_log`, on the same
 * reasoning that table's own migration gives: `audit_log.business_id` is NOT
 * NULL and its RLS policy is keyed on the session tenant, and every row this
 * table describes belongs to a business `TenantDeletion` has already destroyed
 * — there is no tenant to write the entry under, and a platform-scoped act has
 * nowhere else to go. APPEND-ONLY at the model layer, like `audit_log`,
 * `registry_changes` and `credential_changes`.
 *
 * ⚠️ **NO FOREIGN KEY TO `gbp_account_bindings`, AND FOR A SHARPER REASON THAN
 * THAT TABLE'S OWN** (4730). A *successful* revocation deletes the binding —
 * {@see GbpConnections::revokeOwedGrants()}'s
 * order-is-the-point rule — so a foreign key would destroy the one row proving
 * the grant was ever ended, at the exact moment ending it succeeded. The
 * `account_ref`, `business_id` and `location_id` are copied onto every row
 * instead, so the log survives its own subject.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gbp_grant_revocation_attempts', function (Blueprint $table): void {
            $table->id();

            // The vendor account, copied rather than referenced — see the class
            // docblock. Not unique: the same account is attempted again every
            // night it keeps failing.
            $table->string('account_ref');

            // Copied from the binding at the moment of the attempt, for the same
            // reason `zernio_account_days.business_id` carries no foreign key
            // (4730): the business this describes no longer exists by the time
            // any row here is written.
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('location_id');

            // A string cast to App\Enums\GbpRevocationOutcome, never a database
            // enum (CLAUDE.md).
            $table->string('outcome');

            // GbpRequestFailed::$reason on a failure — the vendor's stable
            // machine-readable code, never its message or response body. Null
            // on a success, where there is nothing to explain.
            $table->string('reason')->nullable();

            // 'system' for the nightly sweep and the immediate revocation
            // TenantDeletion attempts at the moment of erasure; 'user:<id>' for
            // an operator's own retry from the screen — audit_log's vocabulary,
            // read the same way here.
            $table->string('actor');

            $table->timestamp('created_at');

            // ⚠️ **THE SCREEN CORRELATES ON THE WHOLE TRIPLE, NOT ON THE
            // ACCOUNT ALONE** — this comment said otherwise until 5103. 5075
            // changed `GbpConnections::attemptsFor()` to match `account_ref`
            // **and** `business_id` **and** `location_id`, because one Zernio
            // account can be bound to more than one business over its life, and
            // matching on the account alone renders one tenancy's failure
            // history under another's row.
            //
            // The index still leads on `account_ref` — the selective column of
            // the three — and the two remaining predicates filter a handful of
            // rows. Deliberately not widened: the steady state of this whole
            // obligation is an empty table, and an index nobody's query shape
            // needs is a write cost with no reader.
            $table->index(['account_ref', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gbp_grant_revocation_attempts');
    }
};
