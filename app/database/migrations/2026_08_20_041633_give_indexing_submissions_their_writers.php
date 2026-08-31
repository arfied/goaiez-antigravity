<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The three columns `indexing_submissions` needs before anything may write to it
 * — `BUILD-PLAN` §2.11.3 slice E, decisions 5680–5699.
 *
 * ⛔ **THE TABLE SHIPPED WITH STAGE 0 AND HAS HAD ZERO WRITERS SINCE** — 272's
 * shape, and §2.11.1's own audit of the tree says so. So this migration follows
 * 5525's rule from the other end: a column lands in the migration that gives it
 * a writer, and these three land now because slice E is the writer.
 *
 * ⚠️ **ALL THREE ARE `NOT NULL` AND THAT IS ONLY SAFE BECAUSE THE TABLE IS
 * EMPTY EVERYWHERE.** Nothing has ever inserted a row — the chokepoint lint that
 * arrives with this slice is what keeps that true from here — so there is no
 * back-fill to argue about and no nullable-then-tighten dance. A later slice
 * adding a fourth column to a table that now *does* hold rows does not get this
 * freedom, which is the reason to spend it here rather than defer.
 *
 * ## `location_id`
 *
 * `29` §2 rule 40 makes jobs location-scoped, `locations.website_url` is where a
 * website lives, and a URL submitted for indexing is a page on exactly one of
 * them. `site_changes` took the same column for the same reason
 * (§2.11.5 conflict 1) and the spec had omitted it there too.
 *
 * ## `attempted_at`, beside the `submitted_at` that was already here
 *
 * ⛔ **A REFUSAL IS THE COMMON CASE IN THIS SLICE AND IT HAS NO SUBMISSION
 * TIME.** Two of the three delivery paths cannot transmit anything until the
 * WordPress plugin exists (5581), so most rows written today record that we
 * declined to send and why. `submitted_at` was the table's only timestamp and
 * its own comment called it *"the datum"*; on a refusal it is null, which would
 * have left the row with no time on it at all and a report unable to order them.
 *
 * So the two columns say two different things and the split is the point:
 * **`attempted_at` is when this application decided**, and **`submitted_at` is
 * when a search engine accepted a request** — null whenever no request was made.
 *
 * ## `reason`
 *
 * The refusal, as one of `App\Enums\IndexingRefusal`'s cases rather than free
 * text in `response`. A staff screen has to render a sentence a person can act
 * on, and reading that out of an untyped vendor blob is how the two drift.
 * `response` keeps its job: what the engine actually said.
 *
 * ⚠️ **THE CHECK IS THE ONE INVARIANT WORTH HAVING AT THE DATABASE AND IT NAMES
 * NO STATUS VALUES.** A row that records a successful submission cannot also
 * carry a refusal — those are contradictory facts about the same attempt, and
 * the pair is what a report reads. Writing the constraint as a list of permitted
 * `status` strings was the obvious alternative and was refused: that is a second
 * source of truth for a PHP backed enum, which is the whole argument
 * `CLAUDE.md` makes against a database enum, wearing a CHECK's clothes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indexing_submissions', function (Blueprint $table): void {
            $table->foreignId('location_id')->after('business_id')->constrained()->cascadeOnDelete();

            // When this application decided, whether or not it sent anything.
            $table->timestamp('attempted_at')->after('submitted_at');

            // One of App\Enums\IndexingRefusal, or null when nothing was
            // refused. A string cast to a PHP backed enum, never a database
            // enum (`CLAUDE.md`).
            $table->string('reason')->nullable()->after('status');

            // The report reads the newest attempts for one location.
            $table->index(['business_id', 'location_id', 'attempted_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE indexing_submissions
                ADD CONSTRAINT indexing_submissions_refusal_excludes_submission CHECK (
                    submitted_at IS NULL OR reason IS NULL
                )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE indexing_submissions DROP CONSTRAINT indexing_submissions_refusal_excludes_submission');

        Schema::table('indexing_submissions', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'location_id', 'attempted_at']);
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn(['attempted_at', 'reason']);
        });
    }
};
