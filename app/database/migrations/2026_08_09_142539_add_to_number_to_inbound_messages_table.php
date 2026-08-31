<?php

declare(strict_types=1);

use App\Http\Controllers\Sms\InfobipInboundController;
use App\Services\Billing\MessageCostLedger;
use App\Services\Sms\InboundMessages;
use App\Services\Sms\NumberHealthService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The number the carrier delivered this inbound message *to* — row 4 slice 6
 * phase 1.
 *
 * ⚠️ **PLAIN TEXT, NEVER HASHED — THE OPPOSITE RULE FROM `value_hash` ON THIS
 * SAME TABLE.** `value_hash` protects the *sender's* number, which is a member
 * of the public's mobile number and PII. This column is *our own* number — the
 * same `phone_numbers.e164` this migration's sibling explains is not PII — and
 * it has to stay comparable against that column in plain text for the question
 * "which of our numbers did this STOP arrive on" to be answerable at all.
 *
 * ⚠️ **IT IS RECORDED AND NOT YET READ, AND THAT IS THE WHOLE POINT OF WRITING
 * IT NOW** (1623). Per-number STOP rate is doc 51 §4's signal and belongs to
 * phase 2, which this slice does not build. Shipping the *reader* first is the
 * failure this codebase keeps recording — a reader over a column nothing writes
 * renders an empty history and reports it as a clean one. So the writer ships
 * first, alone: {@see InfobipInboundController} reads `to` off the webhook
 * payload and {@see InboundMessages::handle()} records it, and nothing consumes
 * it until the phase that needs it exists.
 *
 * ⛔ **THAT PARAGRAPH IS HISTORY AND IS KEPT AND DATED — IT HAS READERS SINCE
 * 2026-08-23** (8437, 4368). Four things in `app/` touch this column and **two
 * of them filter on it**: {@see NumberHealthService::inboundCounts()}
 * (`whereIn` on the `+`-ful and `+`-less forms, plus `whereBetween('created_at')`,
 * once per number per window from a scheduled rollup) and
 * {@see MessageCostLedger::unattributedInboundCount()}
 * (`GROUP BY to_number` over the whole table, no `WHERE` at all). The other two —
 * `CampaignReplyResolver` and `InboundMediaCapture` — read it off a row already
 * in hand, which is not a filter and must not be counted as one when somebody
 * next weighs an index here. **1623's ordering was right and it is finished:**
 * the writer shipped first and the readers arrived four days later.
 *
 * ⛔ **AND THERE IS STILL NO INDEX ON IT — UPHELD 2026-08-23, NOT OVERTURNED**
 * (8328, re-examined at 8437). The refusal is argued at
 * `2026_08_09_043337_create_inbound_messages_table.php` and it stands on the one
 * thing that has not changed: **this table holds no rows in production and
 * nothing has been measured.** ⚠️ **The re-examination made the case WEAKER, not
 * stronger**, which is why it is written here rather than acted on — only one of
 * the two filtering readers could use `(to_number, created_at)` as a seek at
 * all; the other groups the whole table, where the planner's choice depends on
 * the table's size and statistics rather than on the index existing. **Overturn
 * this with an `EXPLAIN` against real volume, never with a paragraph.**
 *
 * Nullable, for two separate reasons and both of them matter. Every row written
 * before this migration has no receiving number recorded — the controller never
 * read the field at all, which is a stronger statement than "read it and threw
 * it away" and is why a backfill is impossible rather than merely skipped. And a
 * payload that genuinely omits `to` must not cost the STOP or reply sitting
 * beside it in the same batch: the controller's rule is "skip the entry, not the
 * batch", and a NOT NULL column would turn a missing sending number into a
 * discarded opt-out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbound_messages', function (Blueprint $table): void {
            $table->string('to_number')->nullable()->after('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('inbound_messages', function (Blueprint $table): void {
            $table->dropColumn('to_number');
        });
    }
};
