<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fields a change set deliberately did not carry, and why — decision 5772.
 *
 * ⛔ **IT LANDS WITH ITS WRITER, WHICH IS 5525's RULE AND THE REASON IT IS NOT
 * IN THE CREATING MIGRATION.** `App\Services\Actuation\SiteChanges::open()`
 * fills it on the same call that opens the row, from
 * `App\Services\Actuation\ChangeSet::$withheld`, and
 * `App\Services\Content\Publishing` is what puts a value in it today: a growth
 * page's meta description, which **WordPress core REST cannot write at all**
 * (5591) because it is a plugin's registered post meta and reaches REST only
 * where that plugin passed `show_in_rest`.
 *
 * ⛔ **THE ALTERNATIVE WAS A SILENT OMISSION AND THAT IS WHAT THIS COLUMN
 * EXISTS TO REFUSE.** Decision 5753(a) found that naming the field refuses the
 * whole publish; dropping it quietly instead would publish a page whose meta
 * description nobody ever set, with no record anywhere that it was intended.
 * A refusal nothing surfaces is 272 with a green suite (1222), so the fact is
 * written where it survives — beside the change it belongs to, on an
 * `application`-tenant-scoped table with RLS already on it, and into the
 * append-only audit entry `apply()` writes.
 *
 * ⚠️ **FIELD NAMES AND FIXED ADAPTER REASONS, NEVER A VALUE.** Everything else
 * on this table's owner-facing surfaces obeys the same rule: a snapshot read off
 * a live site can contain anything that was on that page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_changes', function (Blueprint $table): void {
            // ⚠️ **DEFAULTS TO `{}` RATHER THAN BEING NULLABLE.** "Nothing was
            // withheld" and "nobody recorded whether anything was withheld" are
            // the same answer for every row that existed before this migration,
            // and a nullable column would invite a reader to tell them apart
            // when it cannot. Empty is the honest value in both cases.
            $table->jsonb('withheld_fields')->default('{}');
        });
    }

    public function down(): void
    {
        Schema::table('site_changes', function (Blueprint $table): void {
            $table->dropColumn('withheld_fields');
        });
    }
};
