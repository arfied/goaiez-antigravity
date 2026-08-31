<?php

declare(strict_types=1);

use App\Enums\TenantDeletionReason;
use App\Services\Tenant\TenantDeletion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `28` §9.5's Delete: the request, the two-person rule, and the cooling window.
 *
 * ## ⚠️ Platform-scoped, and this one has the strongest reason of the three
 *
 * `impersonation_sessions` (562) and `registry_changes` (509) are platform-scoped
 * because their reader has no tenant. This table has a harder requirement: **it
 * has to survive the tenant it names.** A record of a deletion that is destroyed
 * by that deletion is not a record. So it takes neither `BelongsToTenant` nor
 * RLS, and is held by the admin gate and a chokepoint lint instead — the posture
 * `staff_events` established (741).
 *
 * ⚠️ **THAT IS WHY THERE ARE TWO COLUMNS FOR ONE FACT.** `business_id` is a real
 * foreign key and goes **null** when the business is deleted; `business_ref`
 * holds the same integer forever and is not a key at all. Neither alone works:
 * a key alone loses the subject at the exact moment the row becomes evidence,
 * and a bare integer alone would let a request be filed against a business that
 * never existed. `audit_log.actor`'s "a label and not a key" convention, applied
 * to a subject rather than an actor.
 *
 * ## The four tables that outlive the tenant
 *
 * `businesses` has 51 dependents: 78 `cascadeOnDelete` and 19 `nullOnDelete`
 * carry almost all of it. Four are `restrictOnDelete` and each was made so
 * deliberately — `impersonation_sessions` (twice), `staff_events.subject_user_id`
 * and `stripe_customers.business_id`. Two of those four carry a comment written
 * *for this slice*, and they are answered in different places because they are
 * different kinds of answer: `stripe_customers` in {@see TenantDeletion}, which
 * refuses to execute while Stripe would still bill, and `impersonation_sessions`
 * in its own migration alongside this one, which drops the foreign key because
 * that table's two triggers forbid both an UPDATE and a DELETE of the row.
 *
 * ## ⚠️ What this does NOT do, stated so the next reader does not assume it
 *
 * `28` §9.5 says *"Erasure is crypto-shred — destroy the per-identity DEK"*.
 * **There is no per-identity DEK in this application.** The only encryption is
 * Laravel's `Crypt` on `platform_credentials` and `oauth_connections`, keyed on
 * a single platform `APP_KEY` — destroying it would erase every tenant at once.
 * So v1 deletes what cascades and leaves standing what must remain, and the word
 * crypto-shred appears nowhere in the code. Recorded rather than approximated,
 * on 531's precedent: a stated gap beats a column with no writer, and a
 * protection asserted before it is true is this codebase's most-repeated defect
 * (314–316).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_deletion_requests', function (Blueprint $table): void {
            $table->id();

            // ⚠️ NULL ON DELETE, WHICH IS THE POINT. Every other business_id in
            // this schema either cascades (78 of them) or restricts (4). This
            // one does neither: the row must survive the deletion it records,
            // and it must not be the thing that prevents it.
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();

            // The same integer, permanently, once the key above has gone. Not a
            // foreign key and deliberately not constrained — see the class
            // docblock.
            $table->unsignedBigInteger('business_ref');

            // A string cast to TenantDeletionReason, never a database enum
            // (CLAUDE.md). Constrained by CHECK below rather than by type.
            $table->string('reason');

            // The operator's own note. Nullable, because a typed reason is the
            // requirement and free text is the courtesy — unlike a suspension,
            // where `28` §9.5 makes the reason mandatory and a CHECK agrees.
            $table->text('detail')->nullable();

            // ⚠️ RESTRICTED, matching staff_events.subject_user_id (743): a
            // leaver cannot be deleted, so the person who asked for an account
            // to be destroyed remains nameable afterwards. The two-person rule
            // is worth nothing if either name can evaporate.
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');

            // The second person. Null until they act; a CHECK below refuses the
            // pair being the same human.
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            // ⚠️ THE CLOCK STARTS AT CONFIRMATION, NOT AT REQUEST. §9.5 words it
            // as "two-person rule, 7-day cooling window" in that order, and a
            // window that began at request would let a single operator start
            // the clock and a second one confirm on day six — delivering a
            // one-day cooling window that reads in the record as seven.
            $table->timestamp('executes_at')->nullable();

            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();

            $table->timestamp('executed_at')->nullable();

            $table->timestamps();
        });

        // The sweep's own query: everything confirmed, uncancelled, unexecuted
        // and due. Partial, because the rows it must never return outnumber the
        // ones it wants by every deletion this platform has ever completed.
        DB::statement(<<<'SQL'
            CREATE INDEX tenant_deletion_requests_due_index
                ON tenant_deletion_requests (executes_at)
                WHERE executed_at IS NULL AND cancelled_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX tenant_deletion_requests_business_index
                ON tenant_deletion_requests (business_ref, id DESC)
        SQL);

        // ⚠️ ONE OPEN REQUEST PER BUSINESS, ENFORCED RATHER THAN CHECKED IN PHP.
        // Two operators filing concurrently is the case a SELECT-then-INSERT
        // cannot close — decision 350 recorded exactly that shape leaving
        // duplicate collapse best-effort under concurrency, and here the
        // duplicate is a second countdown to destroying an account.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX tenant_deletion_requests_one_open_per_business
                ON tenant_deletion_requests (business_ref)
                WHERE executed_at IS NULL AND cancelled_at IS NULL
        SQL);

        $reasons = collect(TenantDeletionReason::cases())
            ->map(fn (TenantDeletionReason $reason): string => "'".$reason->value."'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE tenant_deletion_requests
                ADD CONSTRAINT tenant_deletion_requests_reason_is_known
                CHECK (reason IN ({$reasons}))
        SQL);

        // ⚠️ THE TWO-PERSON RULE, IN THE DATABASE AS WELL AS THE SERVICE.
        // Decision 216's three-layer reasoning, and this is the layer that
        // catches the repair script: `28` §9.5 requires "super_admin + one more
        // admin confirm", and an account destroyed on one person's say-so is
        // not recoverable by editing the row back.
        DB::statement(<<<'SQL'
            ALTER TABLE tenant_deletion_requests
                ADD CONSTRAINT tenant_deletion_requests_needs_two_people
                CHECK (confirmed_by IS NULL OR confirmed_by <> requested_by)
        SQL);

        // Confirmation is one act with three columns, so a half-written one is
        // refused rather than left to be discovered by the sweep.
        DB::statement(<<<'SQL'
            ALTER TABLE tenant_deletion_requests
                ADD CONSTRAINT tenant_deletion_requests_confirmation_is_whole
                CHECK (
                    (confirmed_by IS NULL) = (confirmed_at IS NULL)
                AND (confirmed_at IS NULL) = (executes_at IS NULL)
                )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE tenant_deletion_requests
                ADD CONSTRAINT tenant_deletion_requests_cancellation_is_attributed
                CHECK ((cancelled_at IS NULL) = (cancelled_by IS NULL))
        SQL);

        // ⚠️ NOTHING IS EXECUTED WITHOUT HAVING BEEN CONFIRMED, and nothing is
        // both cancelled and executed. The second is the one that matters: a
        // cancellation that lands in the same second as the sweep must not
        // produce a row claiming both, because that row is the only evidence of
        // which one won.
        DB::statement(<<<'SQL'
            ALTER TABLE tenant_deletion_requests
                ADD CONSTRAINT tenant_deletion_requests_execution_follows_confirmation
                CHECK (
                    (executed_at IS NULL OR confirmed_at IS NOT NULL)
                AND (executed_at IS NULL OR cancelled_at IS NULL)
                )
        SQL);

        // The subject is never forgotten, even after the key goes null.
        DB::statement(<<<'SQL'
            ALTER TABLE tenant_deletion_requests
                ADD CONSTRAINT tenant_deletion_requests_subject_is_recorded
                CHECK (business_ref > 0)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_deletion_requests');
    }
};
