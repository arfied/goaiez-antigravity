<?php

declare(strict_types=1);

use App\Models\DlrErrorBucket;
use App\Services\Sms\NumberHealthService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The truth behind `phone_numbers.health_score` — row 4 slice 6 phase 2,
 * doc `51` §4.4, §10.
 *
 * ⚠️ **THIS IS THE CLAIM PHASE 1 DELETED RATHER THAN SHIPPED EARLY** (1623).
 * `create_phone_numbers_table.php`'s `health_score` column said, correctly at
 * the time, *"NO WRITER IN PHASE 1 … the scoring service and `number_health_
 * daily` are phase 2 and are deliberately not built."* {@see NumberHealthService}
 * is that writer now, and it is the **only** one — a chokepoint lint in
 * `MessagingTest` confines this model and {@see DlrErrorBucket} to
 * that one file, which is I45's "one scorer" made mechanical rather than
 * claimed in a docblock with nothing behind it.
 *
 * ⚠️ **`phone_numbers.health_score` STILL HAS NO READER.** This slice gives it
 * its first writer; rotation weighting and the Degraded state comparison
 * (§5.1, §6.2) are phases 3 and 4, deliberately not built here. Said here
 * rather than left to be inferred, on the same reasoning phase 1 used for the
 * column itself.
 *
 * ⚠️ **`number_id` AND NO `business_id`, THE SAME SHAPE AS `number_state_
 * changes`, WITH A DIFFERENT RLS ANSWER.** A row can score the shared Lane A
 * pool number, which belongs to no tenant — there is no predicate to write,
 * and a policy admitting NULL would admit every row anyway (1632's reasoning
 * for `number_state_changes`, unlike that table). **Unlike that table this one
 * still carries RLS**, ENABLE+FORCEd with `USING (true)` — `phone_numbers`'
 * own posture (`impersonation_sessions`' shape, 562): the reader is
 * `NumberHealthService`, which runs for a resolved tenant's own numbers *and*,
 * while accumulating the shared pool's rollup, with no tenant established at
 * all, so the predicate cannot be `app.business_id` and the application layer
 * is the whole boundary here, exactly as it is for `phone_numbers` itself. See
 * `TenancyTest`'s allowlist entry for the same argument in the place a
 * reviewer of that lint will find it.
 *
 * ⚠️ **`sends` AND `accepted` ARE THE SAME FIGURE TODAY, AND BOTH COLUMNS STAY.**
 * `ReviewInviteSender::sendText()` only ever commits an `outreach_messages` row
 * *after* a successful `Texter::send()` call — the whole create-send-update is
 * one transaction, rolled back on refusal — so nothing outside that
 * transaction ever observes a committed SMS row still `Queued`. Doc 51 §10
 * lists both columns because a future carrier-side accepted/rejected
 * distinction might need the difference; collapsing them in the schema now
 * would lose the ability to express that later for the sake of a column this
 * slice does not need twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_health_daily', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('number_id')->constrained('phone_numbers')->cascadeOnDelete();
            $table->date('date');

            $table->unsignedInteger('sends')->default(0);
            $table->unsignedInteger('accepted')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('failed_filtered')->default(0);
            $table->unsignedInteger('failed_other')->default(0);
            $table->unsignedInteger('stops')->default(0);
            $table->unsignedInteger('replies')->default(0);

            // §4.3's blended score, as it stood the moment this row was last
            // recomputed — a historical snapshot of what phone_numbers.
            // health_score held at that point, not a second live value.
            $table->unsignedTinyInteger('score')->default(100);

            // Sends this day, grouped by outreach_messages.purpose — doc 51
            // §10's context column. No reader in this slice; see the class
            // docblock's "no reader" note, which applies to this column too.
            $table->jsonb('by_class')->nullable();

            $table->timestamps();

            $table->unique(['number_id', 'date']);
        });

        DB::statement('ALTER TABLE number_health_daily ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE number_health_daily FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY platform_and_tenant_readable ON number_health_daily
                FOR ALL USING (true) WITH CHECK (true)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('number_health_daily');
    }
};
