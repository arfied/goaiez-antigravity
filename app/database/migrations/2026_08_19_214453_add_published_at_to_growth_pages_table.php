<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `growth_pages.published_at` — the column that arrives with the writer that
 * fills it (`DATA-MODEL.md` §5.11, slice A's rule at 5525).
 *
 * ⛔ **SLICE C DELIBERATELY DID NOT CREATE IT** — *"`schema_json`,
 * `author_user_id` and `published_at` are deliberately absent … `published_at`
 * lands with slice D"* (5566). This is slice D, and
 * `App\Services\Content\GrowthPages::markPublished()` is the writer.
 *
 * ⛔ **THE CHECK IS A BICONDITIONAL AND THE SECOND DIRECTION IS THE ONE THAT
 * MATTERS** (5552's shape). `published_at IS NOT NULL` exactly when
 * `status = 'published'`:
 *
 *   - a `published` row with no timestamp cannot be measured, and slice H's
 *     whole window — *"baseline 14 days pre-change, measured 14–30 days
 *     post"* — is anchored on when the thing went live;
 *   - a timestamp on a `draft` or `held` row is a claim that text this platform
 *     wrote is already on somebody else's website when it is not, which is the
 *     direction an owner would find out about from a customer.
 *
 * ⚠️ **NO `location_id`, NO SECOND INDEX AND NO NEW POLICY.** The table is
 * already tenant-owned, location-scoped, RLS `ENABLE`+`FORCE`d and indexed on
 * `(business_id, status)`; a released hold is found by that index and a
 * `hold_until` comparison, so the sweep this column serves needs nothing more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('growth_pages', function (Blueprint $table): void {
            $table->timestamp('published_at')->nullable()->after('hold_until');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE growth_pages
                ADD CONSTRAINT growth_pages_published_at_matches_status CHECK (
                    (published_at IS NOT NULL) = (status = 'published')
                )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE growth_pages DROP CONSTRAINT growth_pages_published_at_matches_status');

        Schema::table('growth_pages', function (Blueprint $table): void {
            $table->dropColumn('published_at');
        });
    }
};
