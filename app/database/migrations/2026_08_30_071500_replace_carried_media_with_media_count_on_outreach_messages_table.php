<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `carried_media` becomes `media_count` — the owner ruled on the question the
 * boolean was chosen to avoid asking (decision 12461).
 *
 * ⛔ **THE BOOLEAN'S STATED REASON IS SPENT, AND IT SAID SO ITSELF.**
 * `2026_08_24_200956_add_carried_media_to_outreach_messages_table.php` chose a
 * boolean deliberately and wrote down the condition under which that choice
 * expires: *"A `media_count` would read as though the price were `1 + count`,
 * which is a rule **nobody has made** … at the price of inviting a pricing
 * change no owner ruled on."* **An owner has now ruled on it** — `ceil(characters
 * / 160) + photos`, each photo counting — so the column that could not express
 * the price becomes the one that can.
 *
 * ⚠️ **THE COLUMN IS REPLACED RATHER THAN JOINED.** Keeping `carried_media`
 * beside `media_count` would be two spellings of one fact, and the failure is
 * the ordinary one: they agree until somebody writes only the newer.
 *
 * ## ⛔ WHY THIS IS DDL AND NOT AN `UPDATE`
 *
 * `outreach_messages` carries `ENABLE`+`FORCE` row-level security, and **a
 * migration establishes no tenant** — so `UPDATE outreach_messages SET
 * media_count = …` matches **zero rows and reports success**, which is
 * `CLAUDE.md` §Convention tests' own warning and the reason it is written as a
 * rule rather than as advice.
 *
 * ✅ **The documented escape applies exactly**: a `GENERATED ALWAYS AS … STORED`
 * column is computed by the engine at DDL time, under no row predicate, and
 * `DROP EXPRESSION` then leaves an ordinary writable column holding the computed
 * values. ⚠️ **The escape's own limit — it cannot join another table — does not
 * bite here**, because the computation is self-contained: the new value is a
 * function of the old column and nothing else.
 *
 * ## Null survives, and it still means one thing
 *
 * ⚠️ **`carried_media` NULL BECOMES `media_count` NULL**, because `NULL::int` is
 * `NULL`. Both spellings mean **sent before the column existed** and neither is
 * a claim about that send. ⛔ **This migration does not reprice history**: those
 * rows were charged under the rule in force when they were sent, `credit_ledger`
 * is append-only, and what a send cost is the ledger's to say rather than this
 * column's — the predecessor's sentence, unchanged and still true.
 *
 * ⚠️ **`true` BECOMES `1` AND NOT "AT LEAST ONE".** `RunCampaignJob` is the only
 * composer of media in `app/` and composes at most one item, so on every row
 * this backfill touches the two are the same number. **If that ever stops being
 * true the history is not recoverable from this column** — which is an argument
 * for making the change now, while it is.
 *
 * ## Tenancy
 *
 * No table is created, so `outreach_messages` keeps the RLS `ENABLE`+`FORCE` and
 * the `tenant_isolation` policy its creating migration gave it. A column is
 * orthogonal to row-level security: the policy is evaluated on the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ⚠️ THREE STATEMENTS AND THE ORDER IS THE WHOLE TRICK. The generated
        // column computes for every existing row with no tenant in scope; the
        // DROP EXPRESSION makes it writable; the old column can only go once
        // nothing depends on it.
        DB::statement(
            'ALTER TABLE outreach_messages ADD COLUMN media_count integer '
            .'GENERATED ALWAYS AS (carried_media::int) STORED'
        );
        DB::statement('ALTER TABLE outreach_messages ALTER COLUMN media_count DROP EXPRESSION');
        DB::statement('ALTER TABLE outreach_messages DROP COLUMN carried_media');
    }

    public function down(): void
    {
        // ⚠️ THE REVERSE IS LOSSY AND SAYS SO. A count above one collapses to
        // `true`, because that is all the older column could ever say.
        DB::statement(
            'ALTER TABLE outreach_messages ADD COLUMN carried_media boolean '
            .'GENERATED ALWAYS AS (media_count > 0) STORED'
        );
        DB::statement('ALTER TABLE outreach_messages ALTER COLUMN carried_media DROP EXPRESSION');
        DB::statement('ALTER TABLE outreach_messages DROP COLUMN media_count');
    }
};
