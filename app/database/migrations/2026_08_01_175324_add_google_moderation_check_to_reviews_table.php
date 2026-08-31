<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A Google review can never carry a moderation verdict — row 3 slice D.
 *
 * `29` §2 rule 1: never hold, hide, approve, or moderate a Google review. It is
 * impossible on Google's side and forbidden on ours, and first-party reviews and
 * Google reviews are two pipelines that must never be confused.
 *
 * TODAY THAT RULE RESTS ON ONE RUNTIME READ. `AnalyzeReviewJob::reviewToAnalyse()`
 * checks `source` and returns null for a Google row — correct, tested, and the
 * only layer. `Review::$guarded` is `['id', 'business_id']`, so `source`,
 * `moderation_flags` and `flagged_at` are all mass-assignable: a row created with
 * the wrong `source` and corrected afterwards becomes moderatable, and a repair
 * script, a seeder, a psql session or a future admin screen reaches no layer at
 * all. That is exactly the gap decision 216 closed for "robots respected" and
 * decisions 305–314 closed for the destination rules, each time with a CHECK.
 *
 * SLICE B'S PRECEDENT, AND ITS WARNING. 314–316 found the three-layer thesis
 * *asserted before it was true* — a docblock claiming a database layer that did
 * not exist, on the rule whose damage was least recoverable. The plan for this
 * slice said no migration; the owner overrode it for this one constraint, so the
 * claim and the constraint land together.
 *
 * WHY BOTH COLUMNS. `moderation_flags` is what `Review::displayable()` reads and
 * `flagged_at` is what withholds; either one set against a Google row is a
 * moderation decision recorded about a review nobody is allowed to moderate.
 * Neither is meaningful there even as a coincidence, so both must be null.
 *
 * A PROHIBITION, NOT A WHITELIST, following the Yelp constraint's shape:
 * `source <> 'google'` names the one pipeline the rule is about and constrains
 * nothing else, so a fourth `ReviewSource` case needs no migration. The literal
 * rather than a PHP enum reference because a CHECK is a database object and
 * outlives any class that might be renamed around it — the value is what is
 * stored, and `ReviewSource::Google` is pinned to that string by its own test.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE reviews
                ADD CONSTRAINT reviews_google_is_never_moderated
                CHECK (
                    source <> 'google'
                    OR (moderation_flags IS NULL AND flagged_at IS NULL)
                )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT reviews_google_is_never_moderated');
    }
};
