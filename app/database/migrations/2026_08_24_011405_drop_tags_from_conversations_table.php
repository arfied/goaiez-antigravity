<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop `conversations.tags` — decision 272's shape, and the reason a live
 * chokepoint ran at one arm out of eight (8902, 8903).
 *
 * It shipped with the Stage 0 schema (`DATA-MODEL` §5.9, as `tags TEXT[]`; this
 * table has always held `jsonb`). Since then: `ConversationThreads::openFor()`
 * wrote it, always to `[]` and never to anything else; `Conversation::casts()`
 * declared it an array; `ConversationFactory` seeded `[]`. **Nothing has ever
 * read it** — not a screen, not an export, not a job, not a query.
 *
 * ⛔ **NOTHING SCHEDULES IT EITHER, AND THAT WAS CHECKED RATHER THAN ASSUMED**
 * (8786's method). Every tagging feature in the document set is about
 * `customers.tags`, which is built: `34` §1.1's tags column and §1.2's
 * inline-editable header, `34` §1.3 and `44` §11's bulk tag, `28` §8's segment
 * filters. The specification names two other `tags` columns and neither exists:
 * one is `support_tickets.tags`, which `DATA-MODEL` itself ruled on 2026-08-23
 * *"is not a missing feature sitting in a column"*, and the other sits on a
 * knowledge-base table `DATA-MODEL` §7 records as unbuilt. **A thread tag is
 * named by nothing, anywhere.**
 *
 * ⚠️ **THE COLUMN IS NOT WHAT MAKES THIS WORTH A MIGRATION — THE COLLISION IS.**
 * `CrmTest`'s *"a contact's tags are written in exactly one place"* holds
 * `customers.tags` to `CustomerEditor::retag()`, and it is a bare column name
 * scanned over `app/`. Its own comment says why it runs only the property-
 * assignment arm: *"`Customer` and `Conversation` both cast a `tags` column, so
 * a bare `'tags' =>` pattern would flag every casts() array."* So the second
 * table's dead column is what kept a live chokepoint at **one arm of eight**
 * while its two siblings in the same file ran all of them (1610, 1621) — and
 * six of the seven shapes it could not see are the ones a real second writer
 * takes. **Removing the collision is what lets that lint be as strong as its
 * neighbours**, which is a larger gain than the column is a loss.
 *
 * ⚠️ **A THREAD TAG IS STILL BUILDABLE AND THIS IS NOT A RULING AGAINST ONE.**
 * What it costs is a migration and a decision — and the decision is the point:
 * `MessagingTest`'s census arm and `CrmTest`'s now say out loud what a second
 * table carrying one of these names does to the lint over it, so whoever wants
 * thread tags meets the argument at the moment they add the column rather than
 * eighteen months later at a red build about somebody else's column.
 */
return new class extends Migration
{
    /**
     * ⛔ **THE GUARD RUNS WITH `FORCE` LIFTED, AND THAT IS THE WHOLE REASON IT
     * CAN FAIL AT ALL** — the shape `drop_messaging_mode_and_dedicated_number_id`
     * established on 2026-08-23. Migrations run as `goaiez_owner`, which **owns**
     * `conversations`, and `conversations` is `FORCE ROW LEVEL SECURITY`, so the
     * owner gets the tenant policy too. A migration establishes no tenant, so
     * `SELECT count(*) FROM conversations` returns **0 on a database holding any
     * number of threads** — decision 256's lint that matches nothing, green on
     * every database including the one it exists to stop. `NO FORCE` is lifted
     * for the length of the count and restored in a `finally`, so it comes back
     * on the throwing path rather than only by the surrounding rollback.
     */
    public function up(): void
    {
        $survivors = $this->rowsHoldingATagNobodyWrote();

        if ($survivors > 0) {
            throw new RuntimeException(
                "Refusing to drop conversations.tags: {$survivors} row(s) hold a non-empty value. "
                .'The premise of this drop is that the column was written only ever to the empty '
                .'array, by ConversationThreads::openFor(), and read by nothing (8902). A row '
                .'carrying a tag falsifies that premise, so this is a question for whoever wrote '
                .'it rather than something to drop through. Capture the rows first: '
                ."SELECT id, business_id, tags FROM conversations WHERE tags IS DISTINCT FROM '[]'::jsonb;"
            );
        }

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('tags');
        });
    }

    /**
     * Recreates the column exactly as `create_conversations_table` declared it.
     *
     * ⚠️ It comes back at the END of the table rather than between `csat_score`
     * and `consent_logged_at`, because PostgreSQL appends. Nothing depends on
     * ordinal position; that is a property of `DROP COLUMN` rather than of this
     * migration.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->jsonb('tags')->default('[]');
        });
    }

    /**
     * Count threads holding anything but the empty array, with row-level
     * security genuinely out of the way.
     */
    private function rowsHoldingATagNobodyWrote(): int
    {
        DB::statement('ALTER TABLE conversations NO FORCE ROW LEVEL SECURITY');

        try {
            /** @var object{c: int|string} $row */
            $row = DB::selectOne(<<<'SQL'
                SELECT count(*) AS c
                  FROM conversations
                 WHERE tags IS DISTINCT FROM '[]'::jsonb
            SQL);

            return (int) $row->c;
        } finally {
            DB::statement('ALTER TABLE conversations FORCE ROW LEVEL SECURITY');
        }
    }
};
