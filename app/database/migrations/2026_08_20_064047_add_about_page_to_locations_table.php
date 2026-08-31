<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The page a byline links to — `29` §2 rule 36, made checkable.
 *
 * `BUILD-PLAN` §2.11.3 slice G. **Rule 36: *"auto-published content carries a
 * real author byline linked to a genuine About page."*** Decision 5677 recorded
 * that slice D publishes without one and that it is owed before G, which is the
 * first slice whose writes reach a real website; decisions 5720 and 5729 are the
 * owner's ruling on what the byline says and on what happens when there is
 * nowhere for it to point.
 *
 * ⛔ **IT IS A GATE RATHER THAN A FIELD, WHICH IS WHY THE ADDRESS IS A COLUMN
 * AND THE NAME IS NOT** (5720). The byline *name* is the tenant's own company
 * name, which this schema already holds on `businesses.name` — a second copy
 * would be a column that drifts from the company's own name, in the direction of
 * signing somebody else's article. **What has to be stored is the thing nothing
 * else in this schema knows: where that company describes itself.**
 *
 * ## Confirmed, not inferred — `website_url`'s argument at a second address
 *
 * ⛔ **NOTHING DERIVES `/about` FROM THE WEBSITE ADDRESS.** It is the obvious
 * move and it is decision 1083's failure with a byline on it: a guessed address
 * that happens to resolve — a franchisor's About page, a parked page, a
 * catch-all 200 — would satisfy rule 36's link check while naming a company that
 * is not the publisher. So this pair is pasted and confirmed by a person exactly
 * as `website_url` is, and `App\Services\Tenant\LocationWebsite` is the one
 * writer of both.
 *
 * ## Two CHECKs, and the second is the one a guess would trip
 *
 * ⚠️ **THE BICONDITIONAL IS `locations_website_url_and_confirmation_travel_together`'s,
 * verbatim and for the same reason**: an address nobody vouched for is not a
 * confirmation, and a confirmation with no address reads to every later slice as
 * *"this owner has answered"* — the arm an `->update(['about_url_confirmed_at' =>
 * now()])` produces.
 *
 * ⛔ **AND AN ABOUT PAGE CANNOT EXIST WITHOUT A WEBSITE.** 5720's ruling is *the
 * About page **on their own site***, and the host equality is enforced by the
 * writer because SQL cannot parse a URL. What SQL can say is that the site has to
 * be there at all — which is what stops a row where the About address is the only
 * website-shaped thing on the location, and therefore stops it being read as one.
 *
 * RLS is already `ENABLE`d and `FORCE`d on `locations` by the creating migration
 * and a policy exists; adding columns to a protected table inherits both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            // Where this business describes itself, on its own website.
            $table->string('about_url', 2048)->nullable()->after('cloudflare_detected_at');

            // When a human pasted it and said "yes, that is us". The actor is in
            // the audit log rather than here: `28` §9.1's record is append-only
            // and this column is not.
            $table->timestamp('about_url_confirmed_at')->nullable()->after('about_url');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE locations
                ADD CONSTRAINT locations_about_url_and_confirmation_travel_together
                CHECK ((about_url IS NULL) = (about_url_confirmed_at IS NULL))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE locations
                ADD CONSTRAINT locations_about_url_needs_a_website
                CHECK (about_url IS NULL OR website_url IS NOT NULL)
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE locations DROP CONSTRAINT IF EXISTS locations_about_url_needs_a_website');
        DB::statement('ALTER TABLE locations DROP CONSTRAINT IF EXISTS locations_about_url_and_confirmation_travel_together');

        Schema::table('locations', function (Blueprint $table): void {
            $table->dropColumn(['about_url_confirmed_at', 'about_url']);
        });
    }
};
