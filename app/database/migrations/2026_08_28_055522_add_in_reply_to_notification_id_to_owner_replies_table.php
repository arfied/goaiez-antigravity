<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the owner's reply appears to be answering — wave 40 lane A, decision
 * 10829.
 *
 * ⛔ **AN INFERENCE, AND THE COLUMN NAME IS WHERE THAT LIVES.** No
 * owner-directed message this platform sends carries a reply token, a thread id
 * or a numbered option — decision 10832 refuses the numbered-reply grammar in
 * the same slice and states what has to exist before one is buildable — so
 * **recency is the only signal there is**. `in_reply_to_…` is the email
 * header's own word for exactly this shape: a claim about what a message
 * appears to answer, never a claim about what its author meant.
 *
 * ⚠️ **NULLABLE, AND NULL IS COMMON RATHER THAN EXCEPTIONAL.** It means *"we do
 * not know what this is a reply to"* — every owner who says something
 * unprompted, every owner whose consent predates `owner_notifications`, and
 * every reply that arrives outside
 * `App\Services\Sms\OwnerNotifications::CORRELATION_WINDOW_HOURS`. **It never
 * means "this is not a reply".**
 *
 * ⛔ **NOT BACKFILLED, AND IT COULD NOT HAVE BEEN.** `CLAUDE.md`: a migration
 * establishes no tenant, so an `UPDATE` over a FORCE-RLS table matches zero
 * rows and reports success — and the value would have to be derived by joining
 * a second table, which the generated-column escape cannot do either. Rows that
 * predate this column stay null, which is the honest answer for them: nothing
 * recorded what they might have been answering.
 *
 * ⚠️ **`nullOnDelete()`, BECAUSE THE OTHER SIDE IS PRUNED.**
 * `App\Console\Commands\PruneOwnerChannel` sweeps both tables on one period
 * (`owner_channel.retention_days`), and a reply that outlives its notification
 * by a few hours must degrade to *"we no longer know"* rather than to a
 * dangling id or a refused delete. ⚠️ **A database-level `SET NULL` fires no
 * model event, so `App\Models\OwnerReply`'s append-only guard does not see it**
 * — which is correct here and is the same fact `CLAUDE.md` records about
 * `$guarded`: the guard refuses the hand-written caller and never the engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_replies', function (Blueprint $table): void {
            $table->foreignId('in_reply_to_notification_id')
                ->nullable()
                ->after('body')
                ->constrained('owner_notifications')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('owner_replies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('in_reply_to_notification_id');
        });
    }
};
