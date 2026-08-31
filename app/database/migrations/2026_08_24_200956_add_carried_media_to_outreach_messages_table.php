<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether this send carried media — the fact decision 9182 prices.
 *
 * ⛔ **THE OWNER REVERSED R9's RETAIL SENTENCE ON 2026-08-24** (9182, confirmed
 * at 9193): *"the text is a credit, the media is a credit, a message carrying
 * both is two."* The quantity therefore turns on **what the message carried**,
 * and `outreach_messages` — the row every credit movement points at — had no
 * way to say. `channel`, `purpose`, `lane`, `body`, `status`,
 * `provider_msg_id`, `number_id` and `send_key` are the whole of it; not one of
 * them distinguishes an SMS from an SMS with a picture on it.
 *
 * ## Why a column rather than a parameter
 *
 * `App\Services\Billing\SendCredits` already refuses a caller-supplied flag for
 * the pool restriction, on decision 2548's reasoning: *"a caller-supplied flag
 * could disagree with the row it was sent alongside; this cannot."* The same
 * argument decides this, and one more does: **the ledger row's reference points
 * at this row.** A movement of `-2` explained by nothing on the thing it points
 * at is a figure a tenant can dispute and nobody can answer — which is 9194's
 * shape, *free in this slice and unreconstructible after it*.
 *
 * ⛔ **AND THE TEMPTING ALTERNATIVE IS FORBIDDEN RATHER THAN MERELY WORSE.**
 * `message_cost_entries` already records `OutboundMms` against this same
 * `ref_type`/`ref_id` pair, so the media fact looks derivable from the internal
 * book. It is not available for that: 9182 keeps the two books two books, the
 * cost row is written **after** the send's transaction commits and is skipped
 * entirely while the carrier rate is unset (2547) — which is every deployment
 * today — so reading retail off it would price a send from a book that is
 * empty.
 *
 * ## Nullable, with no default, and both halves are deliberate
 *
 * ⛔ **A `false` DEFAULT WOULD BE A FALSE STATEMENT ABOUT REAL ROWS.** Every
 * campaign send composed by `RunCampaignJob` may have carried a picture, and
 * back-filling `false` would assert of those that they did not. **Null means
 * *sent before this column existed*** and nothing more.
 *
 * ⚠️ **AND IT IS NOT A PRICE AND MUST NOT BECOME ONE.** This migration does not
 * reprice history: those sends were charged one credit under the rule 9182
 * replaced, `credit_ledger` is append-only, and what a send cost is the
 * ledger's to say rather than this column's.
 *
 * ⚠️ **NULL IS UNREACHABLE AT DEBIT TIME**, which is what makes it safe: the
 * debit reads the row its own closure has just inserted, and both writers —
 * `PlatformMessageSender::claim()` and `ReviewInviteSender`'s two
 * `OutreachMessage::create()` calls — state the fact explicitly.
 *
 * ⚠️ **A BOOLEAN RATHER THAN A COUNT, AND THE RESTRAINT IS THE POINT.** A
 * `media_count` would read as though the price were `1 + count`, which is a
 * rule **nobody has made**: 9182 says *"the media is a credit"*, and 9182/9193's
 * own arithmetic — the monthly 500 buying 250 — is two per message and not
 * one per attachment. Only `RunCampaignJob` composes media and it composes at
 * most one item, so a count would record nothing this does not, at the price of
 * inviting a pricing change no owner ruled on.
 *
 * ## Tenancy
 *
 * No table is created, so `outreach_messages` keeps the RLS `ENABLE`+`FORCE`
 * and the `tenant_isolation` policy its creating migration gave it
 * (`2026_07_30_081926_create_outreach_messages_table.php`). A column is
 * orthogonal to row-level security: the policy is evaluated on the row, and it
 * already covers every column this table has or gains.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table): void {
            $table->boolean('carried_media')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table): void {
            $table->dropColumn('carried_media');
        });
    }
};
