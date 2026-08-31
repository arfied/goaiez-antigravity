<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the owner asked for a change back off, before anybody had answered them
 * — decision 5835, `BUILD-PLAN` §2.11.3 slice J.
 *
 * ⛔ **IT LANDS WITH ITS WRITER, WHICH IS 5525's RULE** — `applied_at` (5527)
 * and `withheld_fields` (5780) are the two precedents on this same table.
 * `App\Services\Actuation\SiteChanges::requestUndo()` fills it on the press,
 * `App\Jobs\Actuation\UndoSiteChangeJob` is what then acts, and
 * `App\Livewire\Account\SiteChanges` is what reads it back and renders
 * *"we are undoing this"*. There is no fourth caller, and the chokepoint lint
 * that already guards this table holds it that way.
 *
 * ⛔ **WHY THE STATE IS ON THE ROW RATHER THAN IN THE COMPONENT.** A T1 undo is
 * up to four requests to a customer's own WordPress at fifteen seconds apiece,
 * so it cannot sit inside the web request the owner is waiting on — it is
 * queued. **A queued act with no state on the row is a screen that lies on
 * refresh**: the card would come back offering Undo for a change already being
 * undone, and pressing again is how the owner would find out. 5815's rule one
 * slice along — *the rows are the state and the queue is only a courier*.
 *
 * ⚠️ **IT IS A REQUEST, NEVER AN OUTCOME.** `rolled_back_at` is the outcome and
 * this column never becomes one. The job clears it on a refusal — so a card
 * that cannot say *"undone"* goes back to saying *"undo"* rather than saying
 * *"undoing"* for ever, which is the failure this column would otherwise
 * introduce while fixing another.
 *
 * ⚠️ **NULLABLE RATHER THAN DEFAULTED, WHICH IS THE OPPOSITE CALL FROM
 * `withheld_fields`** (5780). There, *"nothing was withheld"* and *"nobody
 * recorded whether anything was withheld"* are the same answer, and a nullable
 * column would invite a reader to tell them apart when it cannot. Here null
 * carries exactly one meaning — **nobody has asked** — and it is the same
 * meaning for a row that predates this migration as for one written a minute
 * ago.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_changes', function (Blueprint $table): void {
            $table->timestamp('undo_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_changes', function (Blueprint $table): void {
            $table->dropColumn('undo_requested_at');
        });
    }
};
