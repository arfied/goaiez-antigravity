<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The two invariants row 3 slice E states but only enforces in PHP.
 *
 * DECISION 359'S RULING, ONE SLICE LATER. Slice D's plan said "no migration"
 * and the owner overrode it for `reviews_google_is_never_moderated`, on the
 * grounds that a claim and its constraint land together — slice B's 314–316
 * found the three-layer thesis asserted before it was true, on the rule whose
 * damage was least recoverable. Slice E restates two invariants in a service
 * docblock and lands neither, so both arrive here.
 *
 * (a) A GOOGLE REVIEW IS NEVER ROUTED. `29` §2 rule 1: never held, hidden,
 * approved, or moderated. `ReviewRouter::route()` refuses a Google row on its
 * first line — correct, tested, and the only layer. `Review::$guarded` is
 * `['id', 'business_id']`, so all three routing columns are mass-assignable, and
 * `status = 'in_triage'` written against a Google row fails
 * `Review::displayable()`'s status clause. That is *hiding a Google review*,
 * reachable by a seeder, a repair script, or slice I's importer writing an
 * obvious-looking column name. Modelled on the moderation constraint beside it:
 * a prohibition rather than a whitelist, naming Google because rule 1 names
 * Google, so a fourth `ReviewSource` case needs no migration. The literal rather
 * than a PHP enum reference because a CHECK is a database object that outlives
 * any class renamed around it.
 *
 * WHY ALL THREE COLUMNS. `routed_at` alone would leave a decision with no
 * timestamp; `routing_decision` alone would leave a snapshot naming destinations
 * a Google reviewer was never shown. Each is a routing record on a review that
 * has no routing, and none is meaningful there even as a coincidence.
 *
 * (b) A TRIAGE THRESHOLD IS A STAR RATING. Ratings run 1–5, so
 * `triage_threshold = 0` makes `rating <= 0` false for every review that can
 * exist — no location ever reaches triage, and `29` §12.1's "a below-threshold
 * customer always reaches a recovery path" is defeated with no error anywhere.
 * Slice B refused an out-of-range `invite_threshold` in three places (enum,
 * service, CHECK); this is the sibling column, and it has no writer service at
 * all today, which makes the database the only layer there can be.
 *
 * THE RANGE IS 1–5, NOT 0–5, and the difference from `invite_threshold` is
 * deliberate. The two comparisons run in opposite directions: `invite_threshold
 * = 0` means "invite everybody", which is Trustpilot's required setting, while
 * `triage_threshold = 0` means "triage nobody" — one is a configuration, the
 * other is the rule switched off. A tenant who wants no recovery path does not
 * have that option, because the recovery path is not the owner's to remove.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE reviews
                ADD CONSTRAINT reviews_google_is_never_routed
                CHECK (
                    source <> 'google'
                    OR (
                        routing_decision IS NULL
                        AND routed_destinations IS NULL
                        AND routed_at IS NULL
                    )
                )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE autopilot_settings
                ADD CONSTRAINT autopilot_settings_triage_threshold_in_range
                CHECK (triage_threshold BETWEEN 1 AND 5)
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT reviews_google_is_never_routed');
        DB::statement(
            'ALTER TABLE autopilot_settings DROP CONSTRAINT autopilot_settings_triage_threshold_in_range',
        );
    }
};
