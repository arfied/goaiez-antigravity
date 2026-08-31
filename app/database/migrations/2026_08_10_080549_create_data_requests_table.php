<?php

declare(strict_types=1);

use App\Enums\DataRequestKind;
use App\Enums\DataRequestStatus;
use App\Services\Tenant\TenantDeletion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `28` §9.5's data-request queue: GDPR/CCPA asks with due dates.
 *
 * ## Platform-scoped, for the same reason as `tenant_deletion_requests`
 *
 * An erasure request's record has to outlive the tenant it names — otherwise
 * the only evidence that a statutory ask was answered is destroyed by answering
 * it. Consent-audit rows name a tenant that still exists, but one table for the
 * whole queue is simpler than two, and a staff reader has no tenant either.
 * Held by the admin/support gate and a chokepoint lint, not by RLS.
 *
 * ## ⚠️ Erasure is still NOT crypto-shred (1380)
 *
 * Fulfilment routes through {@see TenantDeletion}, which
 * deletes-plus-survivors. The word crypto-shred appears nowhere. Stated on the
 * row's fulfilment note rather than approximated.
 *
 * ## ⚠️ Tenant-wide export is refused, not stubbed (1382)
 *
 * §3.7's engine does not exist. A `tenant_export` kind is accepted into the
 * queue so the ask is dated, then refused with that reason — never a button
 * that produces an empty ZIP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_requests', function (Blueprint $table): void {
            $table->id();

            // Live key — null after an erasure destroys the business.
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();

            // Permanent subject reference (same shape as tenant_deletion_requests).
            $table->unsignedBigInteger('business_ref');

            $table->string('kind');
            $table->string('status');

            // Consent-audit subject. Soft reference: the contact may later be
            // deleted (1540) while the audit row must still name who was asked
            // about. Null for erasure / tenant-export.
            $table->unsignedBigInteger('customer_ref')->nullable();

            // Link to the deletion request this erasure filed, by id only — not
            // a foreign key that would couple two platform stores into a delete
            // cascade neither owns. Null until an erasure is filed through
            // TenantDeletion.
            $table->unsignedBigInteger('deletion_request_id')->nullable();

            $table->timestamp('due_at');

            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');

            // Second person for erasure confirmation / export approval.
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('refused_at')->nullable();

            $table->text('detail')->nullable();
            $table->text('outcome_note')->nullable();

            // Consent-audit trail JSON. Never a full tenant export payload.
            $table->json('result_payload')->nullable();

            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            CREATE INDEX data_requests_queue_index
                ON data_requests (due_at, id)
                WHERE status IN ('open', 'awaiting_deletion')
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX data_requests_business_index
                ON data_requests (business_ref, id DESC)
        SQL);

        $kinds = collect(DataRequestKind::cases())
            ->map(fn (DataRequestKind $kind): string => "'".$kind->value."'")
            ->implode(', ');

        $statuses = collect(DataRequestStatus::cases())
            ->map(fn (DataRequestStatus $status): string => "'".$status->value."'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE data_requests
                ADD CONSTRAINT data_requests_kind_is_known
                CHECK (kind IN ({$kinds}))
        SQL);

        DB::statement(<<<SQL
            ALTER TABLE data_requests
                ADD CONSTRAINT data_requests_status_is_known
                CHECK (status IN ({$statuses}))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE data_requests
                ADD CONSTRAINT data_requests_subject_is_recorded
                CHECK (business_ref > 0)
        SQL);

        // Consent audit needs a contact; erasure / tenant export must not carry one.
        DB::statement(<<<'SQL'
            ALTER TABLE data_requests
                ADD CONSTRAINT data_requests_customer_matches_kind
                CHECK (
                    (kind = 'consent_audit' AND customer_ref IS NOT NULL)
                 OR (kind <> 'consent_audit' AND customer_ref IS NULL)
                )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE data_requests
                ADD CONSTRAINT data_requests_approval_is_whole
                CHECK ((approved_by IS NULL) = (approved_at IS NULL))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE data_requests
                ADD CONSTRAINT data_requests_needs_two_people
                CHECK (approved_by IS NULL OR approved_by <> requested_by)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE data_requests
                ADD CONSTRAINT data_requests_cancellation_is_attributed
                CHECK ((cancelled_at IS NULL) = (cancelled_by IS NULL))
        SQL);

        // One open erasure per business — two concurrent countdowns would be
        // two clocks on one account. Consent audits may stack (different contacts).
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX data_requests_one_open_erasure_per_business
                ON data_requests (business_ref)
                WHERE kind = 'erasure'
                  AND status IN ('open', 'awaiting_deletion')
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('data_requests');
    }
};
