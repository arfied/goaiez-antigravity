<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `autopilot_settings.reply_auto_post_min` changed meaning, and the data did not
 * change with it (1750).
 *
 * ⚠️ THE COLUMN WAS BEING WRITTEN AS MINUTES AND READ AS STARS. `LocationSettings`
 * declared the field *"Wait before posting a reply"*, `min:0, max:120`, while
 * `ReviewReplies::wantsAutoPost()` read the same column as
 * `rating >= reply_auto_post_min` — which is what `29` §210, `17` GBP-04 and
 * `14` §5.8 all say it is. Decision 1725 corrected the form to `min:1, max:5`
 * and left the stored numbers alone, so every row saved through the old screen
 * still holds a duration, and both directions are wrong on the new scale:
 *
 *   - `0` ("post immediately") makes `rating >= 0` true for every review ever
 *     left. Only `AUTO_POST_RATING_FLOOR` stops that publishing an AI reply to
 *     one-star reviews, and a floor is a backstop, not the setting.
 *   - `15`, `30`, `120` make `rating >= 15` false for every review ever left, so
 *     **nothing ever auto-posts, silently** — no error, no feed item, no way to
 *     tell it from a location that simply has not had a review yet.
 *
 * And the owner cannot correct either one: the screen now refuses to save a
 * value outside 1–5, so re-saving the form fails validation on the number
 * already in the database.
 *
 * ⚠️ RESET TO THE DOCUMENTED DEFAULT, NOT CLAMPED, AND THE REASON IS THAT THE
 * OLD NUMBERS CARRY NO THRESHOLD MEANING AT ALL. Clamping would map `0` → `1`,
 * turning *"hold this reply for zero minutes"* into *"auto-post one-star
 * reviews"* — inventing a threshold decision nobody took, and re-creating the
 * exact fail-open 1725 existed to close while relying on a downstream floor to
 * be safe. `15` → `5` would be a coincidence dressed as intent. `5` is what
 * DATA-MODEL §5.4, `14` §5.8 and `17` GBP-01 all specify as the default and what
 * a fresh install gets; it is also the tightest setting the column can express,
 * which is the right way for an ambiguous migration to fail.
 *
 * ⚠️ WHAT THIS CANNOT RECOVER, SAID RATHER THAN IMPLIED. A row holding `1`–`5`
 * is left untouched, because it is indistinguishable from a deliberate
 * post-1725 choice — but "hold for 5 minutes" and "auto-post at 5 stars" are the
 * same byte, so a location saved as `5` minutes before the rename keeps a
 * setting it never chose. There is no column recording which form wrote the row.
 * Pre-launch this is almost certainly empty and the point is moot; that is a
 * fact about today's environment, not about the code, and a migration that
 * no-ops on empty data costs nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * ⚠️ `NO FORCE` FOR THE LENGTH OF ONE STATEMENT, AND DECISION 319 IS WHY
         * THE UPDATE WOULD OTHERWISE BE A NO-OP.
         *
         * 319 records that a migration touching a tenant-owned table with no
         * `app.business_id` set sees **zero rows, not an error** — the policy
         * compares `business_id` to NULL and nothing matches. A migration runs as
         * the table owner, and `FORCE` is precisely what makes the owner subject
         * to that. So the obvious UPDATE would report success, touch nothing, and
         * the CHECK added below would then fail the *deployment* — which reads as
         * a broken migration rather than as unmigrated data.
         *
         * DDL is transactional in Postgres, so a failure anywhere in this
         * migration rolls the suspension back with everything else: the table
         * cannot be left unforced by a crash. The shape is copied deliberately
         * from `2026_08_05_003233_create_stripe_billing_tables`, whose own
         * docblock records that this is not a pattern to reach for by default.
         */
        DB::statement('ALTER TABLE autopilot_settings NO FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            UPDATE autopilot_settings
               SET reply_auto_post_min = 5
             WHERE reply_auto_post_min < 1
                OR reply_auto_post_min > 5
        SQL);

        DB::statement('ALTER TABLE autopilot_settings FORCE ROW LEVEL SECURITY');

        /*
         * ⚠️ THE CONSTRAINT IS WHAT MAKES THE NORMALIZATION STICK, and without
         * it this is a one-time data fix against a column whose meaning lives
         * only in a form's validation rules and a service's comparison operator.
         * That is how the two readings diverged in the first place. `1`–`5` is
         * the full range of a star rating; `AUTO_POST_RATING_FLOOR` narrows what
         * the number can *achieve* and deliberately does not narrow what it may
         * hold, because a tenant tightening to 5 and a tenant relaxing to 1 are
         * both legitimate and only one of them has any effect.
         */
        DB::statement(<<<'SQL'
            ALTER TABLE autopilot_settings
                ADD CONSTRAINT autopilot_settings_reply_auto_post_min_is_a_star_rating
                CHECK (reply_auto_post_min BETWEEN 1 AND 5)
        SQL);
    }

    public function down(): void
    {
        /*
         * The constraint drops; the durations do not come back. Nothing recorded
         * which rows were rewritten or what they held, and reconstructing a
         * minute value from a star rating would be inventing data — the same
         * mistake in the opposite direction. Said here rather than left for
         * whoever runs a rollback to discover.
         */
        DB::statement(
            'ALTER TABLE autopilot_settings DROP CONSTRAINT IF EXISTS autopilot_settings_reply_auto_post_min_is_a_star_rating'
        );
    }
};
