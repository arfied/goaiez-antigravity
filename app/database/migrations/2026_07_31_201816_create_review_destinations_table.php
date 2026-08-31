<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-location, per-destination review invitation config — row 3 slice B.
 *
 * Decision 111 split the threshold per destination before any code existed,
 * because three platforms' terms disagree about who may be invited and applying
 * Google's 5 to Trustpilot puts every tenant's Trustpilot profile at risk of a
 * public compliance flag.
 *
 * THE THREE CHECKS ARE NOT BELT-AND-BRACES. The enum stops a bad value reaching
 * the model, the service stops a bad write reaching the table, and these stop a
 * bad write that reached neither — a repair script, a seeder, a psql session, a
 * future admin screen written by somebody who has not read the service. Decision
 * 216 made "robots respected" a CHECK for exactly this reason: a rule that lives
 * only in application code is one migration away from being bypassed, and the
 * damage here (a Trustpilot profile flagged for cherry-picking) is not
 * recoverable by editing the row back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_destinations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // A string cast to App\Enums\ReviewDestination, never a database
            // enum (CLAUDE.md): a DB enum is a second source of truth that
            // drifts from the PHP one, and Postgres enum values cannot be
            // dropped or reordered once added.
            $table->string('destination');

            // Seeded disabled and stays that way until somebody acts. Google is
            // enabled by PlaceConfirmation::confirm(); the others need a link.
            $table->boolean('enabled')->default(false);

            $table->smallInteger('invite_threshold');

            // NULL for Google, always: its link is derived from
            // location.google_place_id at read time so it cannot drift when a
            // listing merge changes the place id (decision 308).
            $table->text('link_url')->nullable();

            $table->timestamps();

            // One row per destination per location. enable() upserts against
            // this, which is what lets a fourth destination be added later with
            // no backfill migration for existing tenants (decision 309).
            $table->unique(['location_id', 'destination']);
        });

        // Trustpilot's condition of use: invite everybody or invite nobody.
        // Cherry-picking is a violation they enforce publicly, so the tenant's
        // choice is to disable the destination, not to gate it (decision 305).
        DB::statement(<<<'SQL'
            ALTER TABLE review_destinations
                ADD CONSTRAINT review_destinations_trustpilot_threshold_is_zero
                CHECK (destination <> 'trustpilot' OR invite_threshold = 0)
        SQL);

        // Ratings are 1-5. A threshold of 9 means somebody guessed at the scale,
        // and the guess reads as "invite nobody" with no error anywhere.
        DB::statement(<<<'SQL'
            ALTER TABLE review_destinations
                ADD CONSTRAINT review_destinations_threshold_within_scale
                CHECK (invite_threshold BETWEEN 0 AND 5)
        SQL);

        // An enabled destination with nowhere to send anybody is a dead button
        // on a customer-facing page. Google is exempt because its link is
        // derived rather than stored.
        DB::statement(<<<'SQL'
            ALTER TABLE review_destinations
                ADD CONSTRAINT review_destinations_enabled_has_a_link
                CHECK (destination = 'google' OR NOT enabled OR link_url IS NOT NULL)
        SQL);

        // Decision 112's third layer. The enum has no Yelp case and the service
        // never writes one, but neither of those reaches a row inserted by a
        // repair script, a seeder, or a future admin screen that writes this
        // table directly — the exact gap decision 216 closed for "robots
        // respected" with a CHECK rather than a code review note. Yelp
        // solicitation is prohibited outright by their terms, not merely
        // discouraged, so there is no valid non-zero threshold or enabled state
        // to reach for it the way Trustpilot's CHECK does: the row itself must
        // never exist.
        //
        // A PROHIBITION, NOT A WHITELIST. `destination <> 'yelp'` names the one
        // value that can never appear; it does not enumerate the values that
        // may. A whitelist (`destination IN ('google','facebook','trustpilot')`)
        // would need a migration every time a fourth destination is added —
        // exactly the backfill-free growth decision 309 built `enable()`'s
        // upsert to avoid. This constraint needs no migration when that day
        // comes; it only ever needs one if Yelp's terms change, which is not a
        // thing this codebase gets to decide unilaterally anyway.
        DB::statement(<<<'SQL'
            ALTER TABLE review_destinations
                ADD CONSTRAINT review_destinations_yelp_is_not_a_destination
                CHECK (destination <> 'yelp')
        SQL);

        DB::statement('ALTER TABLE review_destinations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE review_destinations FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON review_destinations
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('review_destinations');
    }
};
