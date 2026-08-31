<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The phone number and the address a business has told us are its own.
 *
 * ⛔ **`locations.primary_phone` AND `locations.address` HAVE SHIPPED SINCE
 * STAGE 0 WITH NO WRITER IN `app/`.** `database/factories/LocationFactory.php`
 * filled both and nothing else ever did — `LocationProvisioner` writes
 * `Location::query()->create(['name' => $name])` and stops. That is
 * `businesses.pixel_tenant_id` (4961) and `locations.website_url` (5540) for the
 * third time, and this pair is the most expensive of the three because **two
 * shipped readers already depend on it**:
 *
 *   - `ComplianceReplies::contactFor()` answers a carrier-mandated HELP text
 *     from `primary_phone`. It has returned null for every real tenant since the
 *     day it shipped, so the platform fallback beside it — designed for the
 *     exception — has fired in **100% of cases**, and every member of the public
 *     asking *"who is texting me and how do I reach them"* has been routed to
 *     `support@goaiez.com` instead of to the business.
 *   - `AgentSkills::hasPlacesData()` grounds the assistant's directions skill on
 *     `address`. It has been permanently false, so skill 2 is dark for every
 *     tenant in production while every test lights it from the factory.
 *
 * **Both suites are green throughout**, which is `CLAUDE.md`'s opening sentence.
 *
 * ## Two confirmations, and the CHECKs are biconditional
 *
 * ⛔ **A VALUE WITHOUT A CONFIRMATION IS UNREPRESENTABLE, AT THE DATABASE** —
 * `locations_website_url_and_confirmation_travel_together`'s rule (5540), and it
 * matters more here than it did there. What these two columns are *for* is
 * telling a stranger where a business is and what number to ring, and the one
 * thing this schema must be able to say about them is **who said so**.
 * `App\Services\Tenant\LocationDetails::state()` types its confirmation as PHP's
 * literal `true` (220), so the application cannot write one without a human
 * having said so; the CHECK is what says the same thing to a repair script, a
 * seeder and a factory.
 *
 * ⚠️ **BOTH DIRECTIONS ARE LOAD-BEARING AND FOR DIFFERENT REASONS.** A value
 * with no confirmation is a number nobody vouched for. A confirmation with no
 * value is a vouching for nothing, and it is the arm an
 * `->update(['address_confirmed_at' => now()])` produces.
 *
 * ⛔ **AND THE SET TIMESTAMP IS THE WHOLE OF THE PROVENANCE, WHICH IS ONLY
 * HONEST WHILE NOTHING DERIVES A VALUE.** `PlaceSummary` already carries
 * `formattedAddress` and `nationalPhoneNumber` from Google, and offering one as
 * a pre-fill is a reasonable future slice. **The moment anything does, a set
 * confirmation stops distinguishing *the owner typed this* from *the owner did
 * not object to what we showed them*** — and rows 13 and 14 are a consistency
 * engine whose entire job is to compare this record against Google. If this
 * record is Google's copy of the business, the engine spends its life checking
 * Google against itself. **The slice that adds a derived source adds a source
 * column with it**; until then there is no derivation and a set timestamp means
 * exactly one thing. Raised for a ruling at decision 6106.
 *
 * ## No length limit at the database, deliberately
 *
 * The columns are the existing `varchar(255)`. The real bounds are the writer's
 * — 32 characters for a phone and 200 for an address — and they are there
 * because a **320-character carrier field limit** sits downstream
 * (`ComplianceReplies::CARRIER_FIELD_LIMIT`, 3284) rather than because Postgres
 * needs one. A CHECK restating them would be a second copy of a number that
 * belongs to a message format, and the day the format changes the two disagree.
 *
 * RLS is already `ENABLE`d and `FORCE`d on `locations` by the creating
 * migration, and a policy exists; adding columns to a protected table inherits
 * both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            // When a human typed the number and said "yes, that is how customers
            // reach us". The actor is in the audit log rather than here: `28`
            // §9.1's record is append-only and this column is not.
            $table->timestamp('primary_phone_confirmed_at')->nullable()->after('primary_phone');
            $table->timestamp('address_confirmed_at')->nullable()->after('address');
        });

        // ⚠️ **THE EXISTING ROWS.** Nothing in `app/` has ever written either
        // column, so on any real deployment every row is null on both sides and
        // both CHECKs hold at creation. A tree seeded from a factory is the only
        // place they can fail, which is exactly the population that should be
        // failing them — see `LocationFactory`.
        DB::statement(<<<'SQL'
            ALTER TABLE locations
                ADD CONSTRAINT locations_phone_and_confirmation_travel_together
                CHECK ((primary_phone IS NULL) = (primary_phone_confirmed_at IS NULL))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE locations
                ADD CONSTRAINT locations_address_and_confirmation_travel_together
                CHECK ((address IS NULL) = (address_confirmed_at IS NULL))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE locations DROP CONSTRAINT IF EXISTS locations_address_and_confirmation_travel_together');
        DB::statement('ALTER TABLE locations DROP CONSTRAINT IF EXISTS locations_phone_and_confirmation_travel_together');

        Schema::table('locations', function (Blueprint $table): void {
            $table->dropColumn(['address_confirmed_at', 'primary_phone_confirmed_at']);
        });
    }
};
