<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Our stop. `28` §9.5's *Suspend* — compliance and abuse — given the state it
 * needs.
 *
 * ⚠️ THIS IS NOT A BIGGER PAUSE, AND `BUILD-PLAN` §2.8 SAYS SO IN ADVANCE:
 * *"different trigger, different actor, and a login that shows a status page,
 * because an owner who can resume their own suspension has not been
 * suspended."* Three columns rather than reusing the pause's three, for reasons
 * that are all consequences of that sentence:
 *
 *   - **Both can be true at once**, and each has to survive the other ending.
 *     A tenant who paused themselves last week and is suspended today must
 *     still be paused when the suspension is lifted — one set of columns would
 *     make lifting a suspension silently start their account.
 *   - **They are cleared by different people.** The owner clears a pause from
 *     `/account`; only `super_admin` and `ops_admin` clear a suspension.
 *   - **The audit answers different questions.** "Who stopped this account"
 *     has two answers now, and folding them loses which one is being asked.
 *
 * ⚠️ ON `businesses`, AND THE ROW-LEVEL SECURITY QUESTION IS ANSWERED RATHER
 * THAN AVOIDED. `businesses` is `ENABLE`+`FORCE`d with `tenant_isolation`
 * (USING and WITH CHECK on `app.business_id`) and `owner_lookup` (SELECT only,
 * on `app.user_id`). A support agent has neither by default — which is decision
 * 569's wall, refused four times — but they are not writing this from nowhere:
 * `AccountDirectory` opens an account **by reference** through
 * `Tenancy::actingAs()`, exactly as `TenantPause` already does for support's
 * pause under §9.5. Establishing the tenant satisfies both halves of
 * `tenant_isolation`, so this needs **no third policy and no widening**.
 *
 * The platform-scoped-store precedent (509's `registry_changes`, 741–742's
 * `staff_events`) deliberately does **not** apply here, and the difference is
 * one fact: those exist because their record has **no tenant at all** —
 * `audit_log.business_id` is `NOT NULL` and internal staff belong to no
 * business. A suspension names its tenant. So `audit_log` can hold its history
 * and this table can hold its state, and decision 742's *"the store that grows
 * a third meaning becomes `audit_log` without the tenant boundary"* is honoured
 * by not making `staff_events` hold this.
 *
 * ## What the CHECK enforces, and where it is stricter than the pause's
 *
 * All three columns move together, `businesses_pause_is_attributed`'s pattern.
 * ⚠️ **`suspension_reason` is INSIDE the constraint, where `pause_reason` is
 * outside it**, and that asymmetry is the whole difference between the two
 * controls. Decision 825: an owner pressing their own stop button owes nobody a
 * sentence, so the pause's reason is optional. `28` §9.5 words suspension as
 * *"with typed reasons and role gates"* — it is always somebody acting on
 * another company's account, for cause, and a suspension nobody can explain is
 * the state a compliance review would most want and least be able to
 * reconstruct. Decisions 216, 359 and 383's pattern: the claim and the
 * constraint land together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->timestampTz('suspended_at')->nullable();

            // `audit_log`'s own vocabulary — `user:14`, `support:9` — never a
            // foreign key, for `businesses.paused_by`'s reason: the person who
            // suspends an account belongs to no business, so a foreign key into
            // a tenant-scoped read would be unreadable from inside the tenant
            // it names.
            $table->string('suspended_by')->nullable();

            // 500, matching `pause_reason`. It lands in `audit_log`, which is
            // append-only forever, so an unbounded box lets whoever fills it
            // paste a document into a table nothing can edit afterwards.
            $table->string('suspension_reason', 500)->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE businesses
                ADD CONSTRAINT businesses_suspension_is_attributed
                CHECK ((suspended_at IS NULL AND suspended_by IS NULL AND suspension_reason IS NULL)
                    OR (suspended_at IS NOT NULL AND suspended_by IS NOT NULL AND suspension_reason IS NOT NULL))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE businesses DROP CONSTRAINT businesses_suspension_is_attributed');

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn(['suspended_at', 'suspended_by', 'suspension_reason']);
        });
    }
};
