<?php

declare(strict_types=1);

use App\Enums\BrandRegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A tenant's own 10DLC filing — the first of decision 3310's three
 * preconditions.
 *
 * ⛔ **THE PLATFORM'S OWN BRAND IS NOT IN HERE AND MUST NEVER BE.** GOAIEZ is
 * registered once, ours, and `phone_numbers` with a null `business_id` is what
 * represents it. Every row in this table is a *tenant* filing on their own
 * account, which is the only thing 3310 accepts: *"they need to buy credit and
 * finish 10dlc on there number they can not use a go ai ez number for this they
 * must have there own."*
 *
 * ## What is deliberately not stored
 *
 * ⛔ **NO EIN, NO TAX ID, NO LEGAL ENTITY ADDRESS, NO CONTACT NAME.** A real TCR
 * brand filing carries all four and this table carries none of them, because
 * `CLAUDE.md`'s standing rule is that sensitive data stays off every path it does
 * not have to be on — and the only question this application asks of a filing is
 * *"may this tenant send on their own brand right now"*, which the status and the
 * two provider references answer completely. The vendor holds the filing; we hold
 * the answer. Adding those columns later would need a reason this slice does not
 * have, and once they exist every export, screen and log line inherits them.
 *
 * ⚠️ **NO `submitted_by` FOREIGN KEY**, on `campaigns.created_by`'s and
 * `customer_imports.attested_by`'s precedent: the record has to survive the user
 * row being deleted, and an actor string is what every other service in this
 * codebase already passes.
 *
 * ## The two CHECKs are the load-bearing part
 *
 * ⛔ **`approved` MEANS BRAND *AND* CAMPAIGN.** A brand approved with no approved
 * campaign routes nothing, so a status of `approved` with either provider
 * reference missing would be a green light for a route that does not exist — and
 * the reader is a send-time guard on marketing traffic. The CHECK is decision
 * 216's second layer: the service refuses it and this refuses it again, because
 * `status` is one `update()` away from anywhere.
 *
 * ⚠️ **ONE LIVE FILING PER TENANT, AS A PARTIAL UNIQUE INDEX.** Two `approved`
 * rows would make "is this tenant approved" a question with two answers and a
 * guard that reads whichever sorted first. Rejected rows are excluded from the
 * index deliberately: a refused filing is history somebody will be asked about,
 * and a re-file is a new row rather than an edit of the one that says why the
 * carriers said no.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schema::create removed for brand_registrations to fix duplicates

        $statuses = collect(BrandRegistrationStatus::cases())
            ->map(fn (BrandRegistrationStatus $status): string => "'{$status->value}'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE brand_registrations
                ADD CONSTRAINT brand_registrations_status_is_known
                CHECK (status IS NULL OR status IN ({$statuses}, 'pending', 'active'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE brand_registrations
                ADD CONSTRAINT brand_registrations_approved_rows_are_complete
                CHECK (
                    status <> 'approved'
                    OR (
                        provider_brand_id IS NOT NULL
                        AND provider_campaign_id IS NOT NULL
                        AND approved_at IS NOT NULL
                    )
                )
        SQL);

        // A refusal with no reason and no date is a status nobody can act on,
        // and the tenant will ask what to fix.
        DB::statement(<<<'SQL'
            ALTER TABLE brand_registrations
                ADD CONSTRAINT brand_registrations_rejected_rows_say_why
                CHECK (
                    status <> 'rejected'
                    OR (rejected_at IS NOT NULL AND btrim(rejection_reason) <> '')
                )
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX brand_registrations_one_live_per_business
                ON brand_registrations (business_id)
                WHERE status <> 'rejected'
        SQL);

        DB::statement('ALTER TABLE brand_registrations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE brand_registrations FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON brand_registrations
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_registrations');
    }
};
