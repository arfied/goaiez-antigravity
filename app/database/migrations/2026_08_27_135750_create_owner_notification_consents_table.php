<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The account holder's own consent to be texted about their account — the
 * ruling of 2026-08-27 (10540), captured as evidence rather than as a boolean.
 *
 * ⛔ **A TENANT'S OWN RECORD, NEVER A CUSTOMER'S** — `terms_acceptances`' shape
 * exactly (T176 P22, R24), applied to a second account-holder record. A row
 * names a **business** and the person who agreed; there is no `customer_id`
 * here and there must never be one. `ConsentCapture::refuseAccountHolderSurface()`
 * already refuses `CaptureSurface::Checkout` and `CaptureSurface::Signup` from
 * ever reaching `consent_records` on exactly this argument — *"a consent_records
 * row would name the tenant's own owner as one of their own contacts"* — and
 * that argument applies with the same force here: the owner is not one of
 * their own business's customers, `consent_records.customer_id` is NOT NULL
 * and points at `customers`, and writing this consent there would put the
 * account holder in their own CRM, their own review-invite eligibility and
 * every export of "who we may contact".
 *
 * ⚠️ **A SEPARATE TABLE RATHER THAN A ROW IN `consent_records`, FOR
 * `terms_acceptances`' REASON EXACTLY.** This uses `App\Services\Consent\
 * ConsentProof` directly — the same guards `ConsentCapture` builds on — without
 * going through `ConsentCapture` itself, because that class's constructor is
 * the thing that refuses an account-holder surface. `ProofHashDomain::
 * OwnerNotificationConsents` scopes the stored `ip_hash` to this table alone
 * (7888), so it is not joinable against `consent_records.proof->ip_hash` or
 * any other domain's copy of the same address.
 *
 * ⚠️ **THIS TABLE IS EVIDENCE. `owner_notify_numbers` IS THE OPERATIONAL
 * STATE.** The split is `consent_records` / `customers.sms_consent` one more
 * time: this table is append-only and answers "what were they shown, and
 * when" for a regulator or a carrier; `owner_notify_numbers` is the mutable
 * row `PlatformTexter::sendToOwner()` and inbound identification actually
 * read, and it is deliberately NOT tenant-owned — see its own creating
 * migration for why the reverse lookup needs that.
 *
 * TENANT-OWNED, SO ENABLE + FORCE AND A POLICY IN THIS MIGRATION.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_notification_consents', function (Blueprint $table): void {
            $table->id();

            // business_id for the RLS policy, filled from context by
            // BelongsToTenant. ⛔ There is deliberately no customer column —
            // see this migration's docblock.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // sms|whatsapp|email — cast to OutreachChannel, exactly as
            // consent_records is. Every path built this slice is SMS; the
            // column is typed rather than assumed so a future channel does not
            // need a second table.
            $table->string('channel');

            // checkbox — the only method this slice builds, held to that value
            // by ConsentProof's own pre-checked-box guard.
            $table->string('method');

            // Who agreed — 'user:14', the actor string every service in this
            // codebase takes, never a foreign key, so the record survives the
            // user row being deleted (terms_acceptances' reasoning).
            $table->string('accepted_by');

            // The exact disclosure wording version shown, alongside the
            // proof — consent_records' own reason: consent to unknown wording
            // proves nothing.
            $table->string('disclosure_version');

            // { url, ip_hash, user_agent, checkbox_state, disclosure_text }.
            // ip_hash only: raw IP is never stored, anywhere (`29` §2 rule 21).
            $table->jsonb('proof');

            // Append-shaped, the same as consent_records and
            // terms_acceptances: a consent is an event.
            $table->timestamp('created_at')->nullable();

            $table->index(['business_id']);
        });

        DB::statement('ALTER TABLE owner_notification_consents ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE owner_notification_consents FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON owner_notification_consents
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_notification_consents');
    }
};
