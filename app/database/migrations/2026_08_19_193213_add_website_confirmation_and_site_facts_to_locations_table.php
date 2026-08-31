<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The website this platform is allowed to act on, and what we found there.
 *
 * `BUILD-PLAN` §2.11.3 slice B. ⛔ **`locations.website_url` HAS SHIPPED SINCE
 * STAGE 0 WITH NO WRITER IN `app/`** — only `LocationFactory` set it, which is
 * `businesses.pixel_tenant_id`'s shape exactly (4961): every test of a tier
 * scanner would have passed against a URL no real tenant has, and the scanner
 * would have said "we do not know" for every tenant in production with the suite
 * green. This migration gives the column the state a writer needs.
 *
 * ## `website_confirmed_at`, and why the CHECK is a biconditional
 *
 * ⛔ **A URL WITHOUT A CONFIRMATION IS UNREPRESENTABLE, AT THE DATABASE.**
 * Decision 1083 refuses to infer a Search Console property from this very column
 * — *"a business whose site is a page on a franchisor's domain would be silently
 * mapped to the franchisor's whole property"* — and actuation is the same hazard
 * with a write on the end of it: publishing a page to a franchisor's WordPress
 * because we guessed at the address is the failure `29` §2 rule 32 exists to
 * make impossible. `App\Services\Tenant\LocationWebsite::confirm()` types its
 * confirmation as PHP's literal `true` (220), so the *application* cannot write
 * one without a human having said so; the CHECK is what says the same thing to a
 * repair script, a seeder, and a factory.
 *
 * ⚠️ **BOTH DIRECTIONS ARE LOAD-BEARING AND FOR DIFFERENT REASONS.** A URL with
 * no confirmation is an address nobody vouched for. A confirmation with no URL is
 * a vouching for nothing, which would read to every later slice as "this owner
 * has confirmed" — the more dangerous of the two, because it is the arm a
 * `->update(['website_confirmed_at' => now()])` produces.
 *
 * ## The three detection facts, and the one that is easy to leave out
 *
 * `website_scanned_at` is not bookkeeping. Without it `wordpress_detected_at IS
 * NULL` means both *"this is not a WordPress site"* and *"nobody has ever
 * looked"*, and `BUILD-PLAN` §2.11.2 is explicit that a missing observation is a
 * verdict of its own and is never collapsed into a negative one (229). Slice B's
 * tier reading tells the two apart and says different things about them.
 *
 * ⚠️ **`cloudflare_detected_at` ACTUATES NOTHING AND IS RECORDED ANYWAY.** Doc
 * `41` Part 5 makes T2 auto-detect-only in v1 — the edge tier is Stage 7 — and
 * the nameserver advisory that would go with it belongs to row 16h. What this
 * column buys today is that the fact is collected while the probe runs for other
 * reasons, so Stage 7 starts with history rather than with a sweep of every
 * tenant's website on its first day.
 *
 * ⛔ **NO `tier` COLUMN, AND THAT IS THE POINT OF THE SLICE.** The actuation tier
 * is derived from these facts on every read by
 * `App\Services\Actuation\ActuationTiers`, on `messaging_lane`'s reasoning: a
 * stored tier is a column that drifts from the facts that justify it, in the
 * direction of claiming write access this platform no longer has.
 *
 * RLS is already `ENABLE`d and `FORCE`d on `locations` by the creating migration,
 * and a policy exists; adding columns to a protected table inherits both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            // When a human pasted the address and said "yes, that is our
            // website". The actor is in the audit log rather than here: `28`
            // §9.1's record is append-only and this column is not.
            $table->timestamp('website_confirmed_at')->nullable()->after('website_url');

            // When the probe last completed — set whether or not it found
            // anything, which is the whole reason it is separate from the two
            // findings below.
            $table->timestamp('website_scanned_at')->nullable()->after('website_confirmed_at');

            // Positive findings only. Cleared when a scan stops seeing them, so
            // a site moved off WordPress does not leave a permanent claim.
            $table->timestamp('wordpress_detected_at')->nullable()->after('website_scanned_at');
            $table->timestamp('cloudflare_detected_at')->nullable()->after('wordpress_detected_at');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE locations
                ADD CONSTRAINT locations_website_url_and_confirmation_travel_together
                CHECK ((website_url IS NULL) = (website_confirmed_at IS NULL))
        SQL);

        // ⚠️ A FINDING WITHOUT A SCAN IS NOT A FINDING. Nothing writes one
        // today; the constraint is what stops a later slice recording a
        // detection it inferred from somewhere other than looking.
        DB::statement(<<<'SQL'
            ALTER TABLE locations
                ADD CONSTRAINT locations_site_findings_need_a_scan
                CHECK (
                    website_scanned_at IS NOT NULL
                    OR (wordpress_detected_at IS NULL AND cloudflare_detected_at IS NULL)
                )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE locations DROP CONSTRAINT IF EXISTS locations_site_findings_need_a_scan');
        DB::statement('ALTER TABLE locations DROP CONSTRAINT IF EXISTS locations_website_url_and_confirmation_travel_together');

        Schema::table('locations', function (Blueprint $table): void {
            $table->dropColumn([
                'cloudflare_detected_at',
                'wordpress_detected_at',
                'website_scanned_at',
                'website_confirmed_at',
            ]);
        });
    }
};
