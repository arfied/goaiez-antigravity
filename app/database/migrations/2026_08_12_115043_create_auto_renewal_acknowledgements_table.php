<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The distinct acknowledgment of auto-renewal terms California requires
 * (2980–2999).
 *
 * ⚠️ **A SEPARATE TABLE RATHER THAN A ROW IN `consent_records`, FOR
 * `review_phi_consents`' REASON AND ONE MORE.** That table's `customer_id` and
 * `channel` are both NOT NULL, and this acknowledgment has neither — the person
 * ticking it is the **account holder**, not one of their own customers, and it
 * permits no contact of any kind. Writing it there would also recompute
 * `customers.messaging_lane`, whose entire job is to tell platform-captured
 * consent from a tenant's assertion; an agreement about billing has no business
 * taking part in that derivation.
 *
 * ⚠️ **AND IT IS NOT A COLUMN ON `subscriptions` EITHER.** The obvious cheap
 * shape is `auto_renewal_acknowledged_at`, and it is the shape the law
 * specifically does not accept: what has to be provable is the **wording** the
 * person saw, on which **page**, from what **agent**, at what **time** — and a
 * timestamp beside a subscription proves only that a column was written. It is
 * also append-shaped by nature: a tenant who changes term sees the schedule
 * again and acknowledges again, and both records are evidence.
 *
 * ⚠️ **IT IS NOT A BOOLEAN AND MUST NEVER BECOME ONE.** `ConsentProof` refuses
 * a record missing url, ip_hash or user_agent, refuses a raw address at any
 * depth, and refuses a `checkbox_state` that is not `checked_by_user` — the
 * same three guards `consent_records` and `review_phi_consents` pass through,
 * reached through the class 2933 extracted them into rather than through a
 * second copy.
 *
 * TENANT-OWNED, SO ENABLE + FORCE AND A POLICY IN THIS MIGRATION. What one
 * business's owner agreed to is that business's evidence and nobody else's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_renewal_acknowledgements', function (Blueprint $table): void {
            $table->id();

            // business_id for the RLS policy, filled from context by
            // BelongsToTenant.
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // The exact wording version shown. NOT NULL, for consent_records'
            // reason: agreement to unknown wording proves nothing.
            $table->string('disclosure_version');

            // checkbox. A column rather than an assumption, because the proof
            // blob's `checkbox_state` is only meaningful beside it.
            $table->string('method');

            // ⚠️ WHICH TERM WAS BEING BOUGHT, BECAUSE THE ACKNOWLEDGMENT IS
            // ABOUT A RENEWAL CADENCE AND THE TWO CADENCES ARE A YEAR APART.
            // A record that cannot say whether the person agreed to a monthly
            // or an annual renewal answers none of the questions it exists for.
            // A string cast to `BillingTerm`, never a database enum.
            $table->string('term');

            // { url, ip_hash, user_agent, checkbox_state, disclosure_text }.
            // ip_hash only: raw IP is never stored, anywhere (`29` §2 rule 21).
            // NOT NULL — this box is only ever rendered on a page we serve, so
            // an unproved row here is a bug rather than a surface with nothing
            // to prove.
            $table->jsonb('proof');

            // Append-shaped, the same as consent_records and
            // review_phi_consents: an acknowledgment is an event.
            $table->timestamp('created_at')->nullable();

            $table->index(['business_id', 'created_at']);
        });

        DB::statement('ALTER TABLE auto_renewal_acknowledgements ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE auto_renewal_acknowledgements FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON auto_renewal_acknowledgements
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_renewal_acknowledgements');
    }
};
