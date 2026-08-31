<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether one tenant may currently send — T137 `SL-8`'s per-tenant kill switch,
 * and the thing 2102 requires trip **without a human**.
 *
 * ⚠️ **A ROW HERE MEANS PAUSED. NO ROW MEANS SENDING.** The alternative — a row
 * per tenant carrying a boolean — needs a writer at provisioning time, and
 * `CLAUDE.md` records fifteen instances of a control with no writer whose
 * isolation test passes perfectly against a table nothing populates. A tenant
 * created before this table existed, or by a seeder that does not know about it,
 * would have no row; with a boolean that reads as *"not paused"* only by luck of
 * the null handling, and with presence-means-paused it reads correctly by
 * construction.
 *
 * ⚠️ **AND THE FAIL DIRECTION IS THE OPPOSITE OF THE USUAL ONE HERE, ON
 * PURPOSE.** Most gates in this codebase fail closed — an unconfigured webhook
 * secret refuses everything, an unknown state refuses every marketing send. This
 * one fails *open*, because a missing row is the state of every healthy tenant
 * and failing closed would mean no tenant could ever send until something wrote
 * a row for them. The safety here comes from the write being automatic, not from
 * the read being paranoid.
 *
 * ## Why the global halt is not in this table
 *
 * It is `messaging.global_halt` in the defaults registry, which already has an
 * admin screen, an append-only change history and an actor on every write. A
 * platform-scoped row in a tenant-owned, RLS-`FORCE`d table would need
 * `business_id` nullable and a policy admitting NULL — and a policy admitting
 * NULL admits every row, which is how a tenant table quietly stops being one
 * (1632's argument, reached from the other direction). ⚠️ **The two are checked
 * separately and the global one is checked first**, so an operator halting the
 * platform does not have to reason about ten thousand tenant rows.
 *
 * ## Why the trip reason is a column and not a log line
 *
 * 2102 makes this automatic, which means the common case is a tenant paused at
 * 3am by a threshold nobody was watching. The first question at 9am is *why*,
 * and the answer has to be on the row an operator is already looking at — not in
 * a log they have to know to search. `tripped_by` distinguishes the automatic
 * trip from an operator's deliberate pause, because those two need different
 * conversations and the same screen shows both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sending_pauses', function (Blueprint $table): void {
            $table->id();

            // Unique: a tenant is paused or is not. Two rows would mean two
            // answers, and the resume path would clear one of them.
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();

            // A closed vocabulary cast to a PHP backed enum — never a database
            // enum (CLAUDE.md, enforced by a convention test).
            $table->string('reason');

            // Who or what stopped the sending. An actor string, matching every
            // other service in this codebase: a user identifier for a deliberate
            // operator pause, a system actor for the automatic trip.
            $table->string('tripped_by');

            // The rate that caused an automatic trip, as it stood at that
            // moment. ⚠️ **A snapshot, never recomputed** — by the time anybody
            // looks, the window has rolled and the rate that tripped it is gone.
            // Null for a deliberate operator pause, which had no rate.
            $table->unsignedSmallInteger('observed_rate_bp')->nullable();

            // Free text for an operator's own note. ⚠️ **Never a customer's
            // words and never a phone number** — this is read on an admin screen
            // and copied into support conversations.
            $table->text('note')->nullable();

            $table->timestamps();
        });

        DB::statement('ALTER TABLE sending_pauses ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE sending_pauses FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON sending_pauses
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('sending_pauses');
    }
};
