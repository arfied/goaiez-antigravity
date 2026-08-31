<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The reviewer's undertaking not to include health information (2079-2081).
 *
 * ⚠️ A SEPARATE TABLE RATHER THAN A ROW IN `consent_records`, AND THE REASON IS
 * IN THAT TABLE'S OWN COLUMNS. `consent_records.customer_id` and `.channel` are
 * both NOT NULL. This consent has no channel — it is not permission to contact
 * anybody — and it may have no customer at all, because `reviews.customer_id`
 * is nullable and `FeedbackSubmission` creates a customer only when the person
 * supplies an identifier. An anonymous reviewer can tick this box, and there
 * would be nothing to hang their record on.
 *
 * ⚠️ AND WRITING IT THERE WOULD RECOMPUTE A MESSAGING LANE. `ConsentService::
 * record()` calls `refreshDerived()` on every write, which re-derives
 * `customers.messaging_lane`, `sms_consent` and `email_consent` from the
 * consent history. A record that says nothing about messaging would take part
 * in that derivation — and `messaging_lane` is the column whose entire job is
 * to tell platform-captured consent from a tenant's assertion.
 *
 * ⚠️ IT IS NOT A BOOLEAN AND MUST NEVER BECOME ONE. `29` §2 rule 7's proof
 * shape applies here exactly as it does to a messaging consent: the wording
 * version the person actually saw, the timestamp, the URL, a *hashed* IP and
 * the user agent. `ConsentProof` refuses a record missing any of them, and
 * refuses a raw address at any depth.
 *
 * TENANT-OWNED, SO ENABLE + FORCE AND A POLICY IN THIS MIGRATION. A reviewer's
 * undertaking about one tenant's review is that tenant's evidence and nobody
 * else's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_phi_consents', function (Blueprint $table): void {
            $table->id();

            // business_id for the RLS policy, filled from context by
            // BelongsToTenant. review_id rather than customer_id: the subject of
            // this consent is one piece of writing, not a person we may contact.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();

            // The exact wording version shown. NOT NULL, for consent_records'
            // reason: agreement to unknown wording proves nothing.
            $table->string('disclosure_version');

            // checkbox. A column rather than an assumption, because the proof
            // blob's `checkbox_state` is only meaningful beside it.
            $table->string('method');

            // { url, ip_hash, user_agent, locale, checkbox_state,
            // disclosure_text, ... }. ip_hash only: raw IP is never stored,
            // anywhere (`29` §2 rule 21).
            //
            // NOT NULL, unlike consent_records.proof. That column is nullable
            // because an imported or phoned-in consent genuinely has no URL and
            // no user agent to record; this one is only ever captured on a page
            // we render ourselves, so an unproved row here is a bug rather than
            // a surface with nothing to prove.
            $table->jsonb('proof');

            // Append-shaped, the same as consent_records: consent is a history
            // of events, so created_at only.
            $table->timestamp('created_at')->nullable();

            // ⚠️ NOT UNIQUE ON review_id, DELIBERATELY. A person who resubmits
            // the same words collapses onto the same review (FeedbackSubmission
            // ::recentDuplicate()) and may tick the box the second time having
            // left it alone the first. That is a fresh grant with fresh proof —
            // a new timestamp, IP hash and user agent — and the trail is the
            // evidence. The gate asks whether *any* row exists.
            $table->index(['business_id', 'review_id']);
        });

        DB::statement('ALTER TABLE review_phi_consents ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE review_phi_consents FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON review_phi_consents
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('review_phi_consents');
    }
};
