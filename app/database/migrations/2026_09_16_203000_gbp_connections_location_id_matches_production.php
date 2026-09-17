<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The audit's one TYPE difference: production's gbp_connections.location_id is bigint NOT NULL
     * with CONSTRAINT gbp_connections_location_id_foreign FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE;
     * the tree's migration declares string('location_id')->nullable().
     * Measured on production tonight: 2 rows, 0 nulls, integer values (4, 6), the first written by the live connect flow,
     * and the core GbpConnectionFactory already sets 'location_id' => Location::factory().
     * The owner ruled: the tree matches production for this column.
     */
    public function up(): void
    {
        if (! Schema::hasTable('gbp_connections') || ! Schema::hasColumn('gbp_connections', 'location_id')) {
            return;
        }

        $column = DB::table('information_schema.columns')
            ->where('table_schema', DB::raw('current_schema()'))
            ->where('table_name', 'gbp_connections')
            ->where('column_name', 'location_id')
            ->first();

        if (! $column) {
            return;
        }

        if ($column->data_type === 'character varying') {
            DB::statement('ALTER TABLE "gbp_connections" ALTER COLUMN "location_id" TYPE bigint USING NULLIF(location_id, \'\')::bigint');
        }

        if (DB::table('gbp_connections')->whereNull('location_id')->exists()) {
            Log::warning('gbp_connections.location_id has null values; cannot set NOT NULL');

            return;
        }

        if ($column->is_nullable === 'YES') {
            DB::statement('ALTER TABLE "gbp_connections" ALTER COLUMN "location_id" SET NOT NULL');
        }

        $constraintExists = DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::raw('current_schema()'))
            ->where('table_name', 'gbp_connections')
            ->where('constraint_type', 'FOREIGN KEY')
            ->where('constraint_name', 'gbp_connections_location_id_foreign')
            ->exists();

        if (! $constraintExists) {
            DB::statement('ALTER TABLE "gbp_connections" ADD CONSTRAINT gbp_connections_location_id_foreign FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE');
        }

        DB::statement('CREATE INDEX IF NOT EXISTS gbp_connections_location_id_index ON gbp_connections (location_id)');
    }

    /**
     * Reverse the migrations.
     *
     * A documented no-op: the reverse would drop a foreign key the live flow depends on.
     */
    public function down(): void
    {
        // No-op
    }
};
