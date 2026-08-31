<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Decision 112 is reversed at 1160: Yelp is a destination, by the owner's
 * ruling, with 1145's penalty in view.
 *
 * ⚠️ **THE CONSTRAINT IS RE-POINTED, NOT DROPPED, AND THAT IS DECISION 1161'S
 * WHOLE INSTRUCTION.** `29` §12.1 makes a rule about Yelp build-failing, and
 * `BUILD-PLAN` §7 says never weaken one to get green. That rule is about
 * *weakening a test to pass*; this is *the owner changing the policy the test
 * encodes*, which is a different act and has to look different in the diff. So
 * the old CHECK's replacement asserts the new rule at the same layer:
 *
 *   was   destination <> 'yelp'
 *         "the row must never exist"
 *
 *   now   destination <> 'yelp' OR link_url IS NOT NULL
 *         "a Yelp row must carry a confirmed link — it can only be born of the
 *          confirmed-listing path, never of a seed, a default or provisioning"
 *
 * **The new form is exactly the shape a seeded row has.** `rowFor()` writes
 * `link_url = null`, so a provisioning seed, a repair script, or a future admin
 * screen creating a placeholder Yelp row violates this constraint and says so —
 * which is the database catching what `DestinationSettings::seedDefaults()` and
 * `ReviewDestination::isSeededAtProvisioning()` are the first two layers of.
 * Decision 216's reasoning, unchanged: the write path has other writers.
 *
 * ⚠️ It is deliberately NOT `NOT enabled OR link_url IS NOT NULL` — that is the
 * existing `review_destinations_enabled_has_a_link` constraint, and Yelp already
 * inherits it. Duplicating it here would look like a Yelp rule while enforcing
 * nothing Yelp-specific: a *disabled* Yelp row with no link is precisely the
 * seeded row this exists to refuse, and the enabled-has-a-link CHECK permits it.
 *
 * STILL A PROHIBITION RATHER THAN A WHITELIST, for the reason the original
 * migration gives: `destination IN (…)` would need a migration every time a
 * destination is added, which is the backfill-free growth decision 309 built
 * `enable()`'s upsert to avoid. BBB — the fifth of the v1 set (1198) — will need
 * no migration when it lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE review_destinations
                DROP CONSTRAINT review_destinations_yelp_is_not_a_destination
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE review_destinations
                ADD CONSTRAINT review_destinations_yelp_is_never_seeded
                CHECK (destination <> 'yelp' OR link_url IS NOT NULL)
        SQL);
    }

    public function down(): void
    {
        // ⚠️ The reverse is not symmetrical, and it cannot be. Restoring
        // "destination <> 'yelp'" fails against any tenant who has enabled Yelp
        // since this ran, which is correct: rolling back a policy reversal is a
        // policy decision about live tenant configuration, not a schema step.
        // Postgres validates an added CHECK against existing rows, so this
        // surfaces as a failed migration naming the constraint rather than as
        // silent data loss — the honest failure.
        DB::statement(<<<'SQL'
            ALTER TABLE review_destinations
                DROP CONSTRAINT review_destinations_yelp_is_never_seeded
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE review_destinations
                ADD CONSTRAINT review_destinations_yelp_is_not_a_destination
                CHECK (destination <> 'yelp')
        SQL);
    }
};
