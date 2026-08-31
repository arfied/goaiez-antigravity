<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove `locations.last_synced_at` — decision 10183.
 *
 * ⛔ **IT HAS NO WRITER AND NO READER, AND THE LIVE COLUMN OF THE SAME NAME IS
 * ON ANOTHER TABLE.** `gbp_connections.last_synced_at` is written at
 * `GbpConnections::markSynced()` and read at `SyncGoogleReviewsJob:84` to decide
 * whether a run is a backfill or an incremental. This one has been in the schema
 * since the table was created on 2026-07-30 and appears in `app/` exactly once,
 * as a cast on the model — no writer, no reader, no factory state, no seeder, no
 * raw statement, and one mass-assignment site (`LocationProvisioner`) whose key
 * list is a literal `['name' => …]`.
 *
 * ⛔ **A DEAD COLUMN THAT LOOKS EXACTLY LIKE THE LOAD-BEARING ONE IS WORSE THAN
 * NO COLUMN.** A wave-33 lane was warned about the pair in its brief and still
 * had to be told which was which, and `db:column-readers` is **structurally
 * unable to name this one**: two tables carry the name, the tree holds many
 * occurrences of it, and its pigeonhole shortfall is therefore zero — so the
 * platform's own dead-column census scores it alive on evidence that belongs
 * entirely to the other table. That is the fourth blind spot `ColumnReaders`
 * documents, with a live instance in it.
 *
 * ⚠️ **AND THE REPLACEMENT WAS ALREADY ARGUED, IN WRITING, AGAINST THIS COLUMN.**
 * `VisibilitySyncHistory`'s docblock: *"A `last_synced_at` column would be a
 * second source of truth for a fact this table holds, and a tenant-owned column
 * cannot be backfilled by a migration anyway."* The fact lives in
 * `automation_runs`, per run, per location, under the tenant it happened to.
 *
 * ⚠️ **REVERSIBLE, AND `down()` RESTORES A NULL COLUMN RATHER THAN THE DATA.**
 * There is no data: nothing has ever written a value here. A `down()` that
 * pretended otherwise would be the claim this migration exists to remove.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropColumn('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->timestamp('last_synced_at')->nullable();
        });
    }
};
