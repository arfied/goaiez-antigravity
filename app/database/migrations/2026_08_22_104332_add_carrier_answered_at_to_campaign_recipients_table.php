<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the carrier answered about this message — the full stop that retires the
 * question `send_handle` asks (7501).
 *
 * ⛔ **WITHOUT IT A ROW THE CARRIER HAD ALREADY ANSWERED FOR WAS RE-ASKED ABOUT
 * ROUGHLY FORTY-SEVEN TIMES.** `UnknownSendReconciler` counted a refusal, logged
 * it and moved on **writing nothing to the row**, and the sweep selects on
 * status, a non-null handle and a forty-eight-hour window — none of which the
 * pass had changed. So the next hourly pass asked the same question, got the
 * same terminal answer, wrote the same log line and incremented the same printed
 * figure, every hour for two days. **The count an operator read was therefore a
 * count of passes and not a count of contacts**, which is the more expensive
 * half: forty-seven wasted vendor calls are cheap, and a figure that means
 * something other than what it says is what somebody makes a decision on.
 *
 * ## What it records, exactly
 *
 * **The moment this application was told something final about the message.**
 * Three of the four answers end the question and all three stamp it: a send the
 * carrier can name and time (which also becomes `sent`), a message the carrier
 * says was not delivered, and one the carrier says it rejected. The two that do
 * **not** stamp it are the two that are not answers — a handle the vendor has
 * never heard of, and a log line carrying no status at all.
 *
 * ⚠️ **SO ON A PROMOTED ROW IT SITS BESIDE `sent_at` AND MEANS SOMETHING ELSE**:
 * `sent_at` is the carrier's clock and says when the message went, this is ours
 * and says when we found out. A row where the two are thirty hours apart is a
 * row this reconciler rescued, which is the first question anybody reviewing one
 * will ask and the only column that answers it.
 *
 * ## Why a column rather than a status change
 *
 * ⛔ **BECAUSE THE HONEST STATUS FOR AN UNPROMOTABLE ANSWER IS THE ONE THE ROW
 * ALREADY HAS.** `CampaignRecipientStatus` has no case this reconciler may write
 * for *"the carrier says it did not arrive"*: `refused` needs a
 * `SendRefusalReason`, and every case of that enum names a decision **this
 * application** took — a consent gate, a register, a window — so the carrier's
 * answer would arrive dressed as our own. `failed` is worse: it is
 * *outstanding*, and `closeIfFinished()` has already completed the campaign, so
 * it produces a recipient no pass will ever select (the reconciler's docblock
 * argues this at length, and 7377(a) is where re-opening is asked about rather
 * than guessed). **What changed is that the question has an answer, and that is
 * what this column records.**
 *
 * ## Why the write must not touch `updated_at`, which is the trap
 *
 * ⛔ **`updated_at` IS THE ATTEMPT TIME FOR AN `unknown` ROW**, and two readers
 * depend on it: the sweep's forty-eight-hour window, and — the one that
 * matters — `SendCollisionArbiter`, which counts an `unknown` row as a live
 * marketing touch for as long as its `updated_at` sits inside the touch window.
 * **An ordinary `save()` on the arms that leave the status alone would extend a
 * real person's marketing block by a full window, every hour, on the strength of
 * a full stop rather than a send.** The writer therefore disables timestamps for
 * those two writes and a test drives it red. The promoting arm is different and
 * deliberately keeps its timestamps: that row stops being `unknown`, so the
 * clause that reads `updated_at` no longer applies to it at all.
 *
 * ⚠️ **NO CHECK CONSTRAINT AND NO INDEX**, both deliberately. A CHECK tying this
 * to a status would have to be dropped the first time a fourth answer wanted to
 * be remembered, and it constrains nothing anybody can get wrong: the column
 * says a question was answered, never that somebody was or was not messaged,
 * which is what the three existing CHECKs are for. And
 * `campaign_recipients_unresolved` already leads on `updated_at` inside the
 * window, so an answered row leaves the sweep's range on its own within
 * forty-eight hours and never grows the scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table): void {
            // ⚠️ **AFTER `send_handle`, BECAUSE THE TWO ARE ONE PAIR**: the
            // handle is the question this application can ask the carrier, and
            // this is the answer that retires it.
            $table->timestamp('carrier_answered_at')->nullable()->after('send_handle');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table): void {
            $table->dropColumn('carrier_answered_at');
        });
    }
};
