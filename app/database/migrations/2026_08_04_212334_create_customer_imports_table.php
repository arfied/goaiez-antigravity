<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A tenant's uploaded customer list, and the attestation that came with it.
 *
 * ⚠️ **THIS TABLE EXISTS BECAUSE OF DECISION 549, AND IT IS NOT CONSENT.** The
 * owner approved reactivation messaging to a tenant's own customer list on the
 * basis that the businesses have an existing relationship with those people. The
 * argument recorded against that is in 549 and is not repeated here. What this
 * table does is make the claim **evidenced instead of asserted**: who attested,
 * when, in what words, from what address. An EBR claim and an indemnity are
 * worth close to nothing without exactly that record, which is why building it
 * is the first thing that helps whichever way the question is ultimately
 * answered.
 *
 * **A row here never authorises a send on its own.** `ConsentService::permit()`
 * remains the only thing that decides, it reads `consent_records`, and what an
 * import writes there is `captured_by = tenant` — which `CapturedBy`'s own
 * docblock already binds to **Lane B, the tenant's own dedicated number
 * requiring their own TCR brand**. That is decision 550's containment, and it
 * turned out to be written into the enum before this slice existed rather than
 * imposed by it: one tenant's list can never reach the shared toll-free pool,
 * so a carrier block it earns cannot take texting away from tenants who never
 * ran a campaign.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_imports', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Nullable for the same reason customers.location_id is: a
            // single-location tenant has no meaningful choice to record, and a
            // list can legitimately span a tenant's locations.
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            // The published wording the tenant actually agreed to, stored the
            // way consent_records.disclosure_version is. Two attestations of the
            // same statement must compare equal, so the value object refuses a
            // padded one rather than trimming it.
            $table->string('statement_version');

            $table->timestamp('attested_at');

            // Who clicked. A user identifier or a system actor string, matching
            // the `$actor` argument every service in this codebase already
            // takes. Not a foreign key: the evidentiary value is in the string
            // surviving the user row being deleted.
            $table->string('attested_by');

            // ip_hash and user_agent. `29` §2 forbids storing a raw IP, so
            // App\Support\HashedIp is what goes in here — the same shape
            // ConsentCapture::proof carries, and for the same audience.
            $table->jsonb('proof');

            // How many contacts the attestation covers. Recorded because an
            // attestation is about a specific set of people on a specific day;
            // "I confirm these are my customers" with no idea how many were in
            // the file is the kind of evidence that reads as boilerplate.
            $table->integer('row_count');

            $table->string('source');

            $table->timestamps();

            // RLS predicates business_id on every query and Postgres does not
            // index a foreign key automatically — a MySQL habit that does not
            // transfer, and the omission decision 314–316 caught on
            // destination_clicks.
            $table->index(['business_id', 'created_at']);
        });

        // An attestation covering nobody is not evidence of anything, and it is
        // what an import that silently parsed zero rows would leave behind.
        DB::statement(<<<'SQL'
            ALTER TABLE customer_imports
                ADD CONSTRAINT customer_imports_covers_at_least_one_contact
                CHECK (row_count >= 1)
        SQL);

        // The service refuses a blank or padded statement version and so does
        // the value object. This catches the third case both miss: a repair
        // script, a seeder, or a future admin screen written by somebody who has
        // not read either. Decision 216's reasoning, and the damage is the same
        // shape as Trustpilot's — an attestation with no recoverable wording
        // cannot be reconstructed later by editing the row back, because nobody
        // will know what the tenant was shown.
        DB::statement(<<<'SQL'
            ALTER TABLE customer_imports
                ADD CONSTRAINT customer_imports_statement_version_is_present
                CHECK (btrim(statement_version) <> '')
        SQL);

        DB::statement('ALTER TABLE customer_imports ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE customer_imports FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON customer_imports
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_imports');
    }
};
