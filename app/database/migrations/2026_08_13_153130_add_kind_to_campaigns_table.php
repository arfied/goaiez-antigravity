<?php

declare(strict_types=1);

use App\Enums\CampaignKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The column that tells a broadcast from a reactivation — decision 3310.
 *
 * ⛔ **THERE WAS NO SUCH COLUMN, AND THAT IS WHY A GUARD ON BROADCASTS WOULD HAVE
 * GUARDED NOTHING.** Every campaign in this engine is a reactivation; `audience`
 * says how the recipients were chosen and `OutreachPurpose` says the send is
 * marketing, which reactivation also is (2100). A precondition written against a
 * kind that does not exist is `CLAUDE.md`'s 256 exactly — *"a lint, test or gate
 * that matches nothing passes vacuously"* — so the discriminator ships in the
 * same migration as the guard that reads it, and both ship with a writer.
 *
 * ⚠️ **A STRING CAST TO `App\Enums\CampaignKind`, NEVER A POSTGRES ENUM TYPE**
 * (`CLAUDE.md`; an `ConventionsTest` lint fails the build on `->enum(`).
 *
 * ⚠️ **DEFAULTED TO `reactivation`, WHICH IS TRUE OF EVERY EXISTING ROW AND IS
 * ALSO THE SAFE DIRECTION.** A NOT NULL column added to a live table needs a
 * value for the rows already there, and the honest one is the one they all are.
 * The default also fails in the right direction the day a caller forgets to
 * pass a kind: a row that means "reactivation" sends over the platform brand
 * that is already carrying it, whereas defaulting to `broadcast` would put a
 * campaign nobody classified in front of a guard it cannot satisfy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->string('kind')->default(CampaignKind::Reactivation->value);
        });

        $kinds = collect(CampaignKind::cases())
            ->map(fn (CampaignKind $kind): string => "'{$kind->value}'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE campaigns
                ADD CONSTRAINT campaigns_kind_is_known
                CHECK (kind IN ({$kinds}))
        SQL);

        // The runner sweeps outstanding campaigns per tenant and the
        // preconditions are asked per kind; `status` is already in the index
        // beside `business_id`, and this makes "which of this tenant's live
        // campaigns are broadcasts" answerable without a scan.
        DB::statement(<<<'SQL'
            CREATE INDEX campaigns_business_kind_status_index
                ON campaigns (business_id, kind, status)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS campaigns_business_kind_status_index');
        DB::statement('ALTER TABLE campaigns DROP CONSTRAINT IF EXISTS campaigns_kind_is_known');

        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
