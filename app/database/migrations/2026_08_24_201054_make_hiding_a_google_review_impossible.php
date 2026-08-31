<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `reviews` refuses to hide a Google review, and refuses to stop calling one a
 * Google review (decisions 9240–9243).
 *
 * `29` §2 rule 1: Google reviews cannot be **held, hidden, approved, or
 * moderated**. Three of those four verbs already meet a database object on this
 * table — `reviews_google_is_never_moderated` refuses `moderation_flags` and
 * `flagged_at`, `reviews_google_is_never_routed` refuses the routing columns.
 * **`hidden` met nothing.** 9091(c) sized that gap and did not close it:
 * *"`ReviewDisplay::assertDecidable()` is the only layer, and a raw `UPDATE`
 * hiding a Google review is refused by nothing — rule 1's *hidden* verb,
 * unenforced at the database."*
 *
 * ---------------------------------------------------------------------------
 * ⛔ WHY 9091(c) SAYS THE CHECK IS UNBUILDABLE, AND WHY THAT ARGUMENT IS RIGHT
 * ---------------------------------------------------------------------------
 * The obvious constraint is `source <> 'google' OR display_on_website`, on the
 * two CHECKs' own *prohibition, not whitelist* shape. It cannot ship:
 * `display_on_website` is `DEFAULT false`, `ReviewFactory::fromGoogle()` does
 * not set it, and `GoogleReviewIngest` writes `true` in the `create()` payload
 * — so a CHECK is violated by **every factory-built Google review in the
 * suite** and by every real Google row in the instant between the INSERT and
 * the column being written. A CHECK is a statement about **every row**, and the
 * insert state makes that statement false. Nothing about `GoogleReviewIngest`
 * should be rearranged to make it true; that is the tail wagging the dog.
 *
 * ✅ **The property that IS enforceable is a statement about a DELTA, and that
 * is a trigger.** `BEFORE UPDATE` sees `OLD` and `NEW`, so it can refuse an
 * update that *puts a row into* the forbidden state while permitting a row that
 * was already in it. A Google review that has never been shown may stay unshown
 * — nobody is entitled to appear on somebody's website, and that is the case
 * `ReviewDisplay`'s class docblock argues for. What is refused is **taking one
 * down**, which is a different act with a different name.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ WHY *NOT SHOWING* AND *TAKING DOWN* ARE DIFFERENT, WHICH THIS TREE HELD
 * BOTH POSITIONS ON
 * ---------------------------------------------------------------------------
 * `ReviewDisplay`'s class docblock says *"Not showing a Google review in a
 * business's own marketing widget is **not hiding it under rule 1**"*, and
 * `WidgetTest`'s `min_stars_to_show` refusal says the opposite in as many words
 * — *"arming this control would hide low-rated **Google** reviews — and `29` §2
 * rule 1 is that Google reviews can never be held, hidden, approved or
 * moderated."* Both were written honestly and the tree moved underneath the
 * first one. When `ReviewDisplay` was written **no Google review was ever
 * shown**: the absence was uniform, and a uniform absence is not a suppression
 * of anything. `GoogleReviewIngest` then shipped and writes
 * `display_on_website = true` on **every** ingested Google review, on both its
 * insert and its update path — so the platform's own baseline is now *show them
 * all*, and setting one row's column to `false` removes **one named review**
 * from a surface where its siblings still appear. That is FTC 16 CFR §465.7's
 * subset-display shape and rule 1's *hidden* verb, and it is exactly the
 * distinction `WidgetTest` draws when it calls `display_on_website` *"a
 * per-review editorial act on one named review"*.
 *
 * The exemption below is that distinction expressed in SQL, and it is the whole
 * reason this is affordable: **a row already at `(google, not shown)` is left
 * alone; an update that arrives there is refused.**
 *
 * ---------------------------------------------------------------------------
 * ⛔ THE SECOND TRIGGER, AND IT IS THE ONE WITHOUT WHICH THE FIRST IS THEATRE
 * ---------------------------------------------------------------------------
 * **`reviews.source` is a plain `string` with a default and nothing pins it.**
 * No CHECK, no trigger, no generated column; `Review::$guarded` is
 * `['id', 'business_id']` and names it not at all. So every Google protection
 * in this schema — both CHECKs, `ReviewDisplay::assertDecidable()`, and
 * `GbpTest`'s writer chokepoint — keys off a value any `UPDATE` can rewrite,
 * and one statement launders a Google review into a first-party one:
 *
 *     UPDATE reviews SET source = 'first_party', display_on_website = false
 *
 * A guard reading `NEW.source` is walked past by that statement; a guard
 * reading `OLD.source` is not. **This one reads both**, and the pair of
 * triggers below closes the two-statement version as well, in either order —
 * see the ordering note on the second trigger.
 *
 * ⚠️ **THE SHAPE WAS NAMED IN THIS DIRECTORY ON 2026-08-01 AND NOTHING WAS
 * BUILT.** `add_google_moderation_check_to_reviews_table.php`'s own docblock
 * reads *"a row created with the wrong `source` and corrected afterwards
 * becomes moderatable, and a repair script, a seeder, a psql session or a
 * future admin screen reaches no layer at all"* — and then shipped a CHECK
 * that a corrected `source` walks straight out of, because the CHECK's
 * predicate reads the corrected value. 314–316 exactly: the paragraph naming
 * the hazard is what made the constraint beside it read as considered.
 *
 * ⚠️ **IT IS A ONE-WAY PIN AND THAT IS DELIBERATE.** A row may still become a
 * Google row; it may never stop being one. The prohibition-not-whitelist
 * doctrine both CHECKs are written on is the reason — `source <> 'google'`
 * *"names the one pipeline the rule is about and constrains nothing else"* —
 * and DATA-MODEL §5.14 keeps `google_review_id` nullable *"until matched"*, so
 * forbidding the other direction would pre-emptively close a matching path this
 * schema was designed for. The dangerous half of that direction is covered
 * anyway: an update that turns a hidden first-party row into a Google row
 * *arrives* at `(google, not shown)` and the first trigger refuses it.
 *
 * ---------------------------------------------------------------------------
 * THE CONVENTIONS, WHICH ARE THIS SCHEMA'S AND NOT THIS MIGRATION'S
 * ---------------------------------------------------------------------------
 * SQLSTATE `23514` on both, for `legal_documents_freeze_published()`'s reason:
 * it is what a CHECK violation raises, so a caller that already handles *"the
 * database refused this write"* handles these identically — **the rules are
 * check constraints in everything but the mechanism**, which is the only reason
 * they are mechanisms at all.
 *
 * No `TG_OP` branch: both triggers are scoped to `UPDATE` alone, so `NEW` is
 * never `NULL`. `DELETE` is deliberately untouched — `reviews.business_id` is
 * `cascadeOnDelete` and `TenantDeletion::execute()` is a legitimate remover, so
 * a `BEFORE DELETE` trigger here would abort a statutory erasure, which is the
 * trade `make_audit_log_updates_impossible.php` refused for the same reason.
 *
 * The literal `'google'` rather than a PHP enum reference, on the two CHECKs'
 * rule: a trigger is a database object and outlives any class renamed around
 * it. `ReviewSource::Google` is pinned to that string by its own test.
 *
 * `NEW.display_on_website IS NOT TRUE` rather than `NOT NEW.display_on_website`
 * in the **prohibition**, and `= false` in the **exemption**: the column is
 * `NOT NULL` today and both spellings agree, but they disagree on a `NULL` a
 * later migration could introduce, and they are written so that each fails
 * closed — a null neither escapes the prohibition nor earns the exemption.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Rule 1's *hidden* verb ────────────────────────────────────────
        //
        // Read as: an UPDATE may not *produce* a Google review that is not
        // shown, unless the row it started from was already exactly that.
        //
        // The third clause is the whole constraint's licence to exist. Without
        // it this is the CHECK 9091(c) refused, arriving as a trigger and
        // failing on the same population: `ReviewFactory::fromGoogle()` leaves
        // the column at its `false` default, so every fixture that touches a
        // Google review for any unrelated reason — a rating, a reply, a raw
        // payload — would meet this.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION reviews_refuse_hiding_google()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.source = 'google'
                    AND NEW.display_on_website IS NOT TRUE
                    AND NOT (OLD.source = 'google' AND OLD.display_on_website = false)
                THEN
                    RAISE EXCEPTION
                        'reviews row % is a Google review and cannot be taken off the website (`29` §2 rule 1: Google reviews cannot be held, hidden, approved, or moderated). A Google review that was never shown may stay unshown; removing one that is shown is the hidden verb.',
                        OLD.id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER reviews_google_is_never_hidden
                BEFORE UPDATE ON reviews
                FOR EACH ROW
                EXECUTE FUNCTION reviews_refuse_hiding_google()
        SQL);

        // ── 2. The value every other Google guard reads ──────────────────────
        //
        // ⚠️ **NAME ORDER IS BEHAVIOUR HERE, NOT STYLE.** Postgres fires row
        // triggers on one event in alphabetical order of trigger name, and
        // `reviews_google_is_never_hidden` sorts before
        // `reviews_source_never_leaves_google`. So on the one-statement
        // laundering — `SET source = 'first_party', display_on_website = false`
        // — the first trigger sees `NEW.source = 'first_party'`, correctly does
        // not fire, and **this one is what refuses the statement**. Its message
        // is therefore the one a person laundering a row by hand reads, which
        // is why it names the act rather than the column.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION reviews_refuse_source_leaving_google()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF OLD.source = 'google' AND NEW.source IS DISTINCT FROM OLD.source THEN
                    RAISE EXCEPTION
                        'reviews row % came from Google and stays a Google review. `source` is what reviews_google_is_never_moderated, reviews_google_is_never_routed and ReviewDisplay::assertDecidable() all read, so rewriting it moves the row out of every protection `29` §2 rule 1 has.',
                        OLD.id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER reviews_source_never_leaves_google
                BEFORE UPDATE ON reviews
                FOR EACH ROW
                EXECUTE FUNCTION reviews_refuse_source_leaving_google()
        SQL);
    }

    /**
     * Both triggers and both functions, each by name.
     *
     * `legal_documents`' `down()` records the reason and
     * `make_audit_log_updates_impossible.php` repeats it: a trigger goes with
     * its table and a function does not, so a leftover function is invisible
     * and would silently survive a rebuild. Here the table outlives both, so
     * all four objects are named.
     *
     * Reversible on purpose, on the audit log's own argument: `migrate:rollback`
     * is not the threat model — anybody who can run it can run `DROP TRIGGER`
     * — and an irreversible `up()` cannot be exercised twice, which is how the
     * mutation for these triggers is run.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS reviews_google_is_never_hidden ON reviews');
        DB::statement('DROP TRIGGER IF EXISTS reviews_source_never_leaves_google ON reviews');

        DB::statement('DROP FUNCTION IF EXISTS reviews_refuse_hiding_google()');
        DB::statement('DROP FUNCTION IF EXISTS reviews_refuse_source_leaving_google()');
    }
};
