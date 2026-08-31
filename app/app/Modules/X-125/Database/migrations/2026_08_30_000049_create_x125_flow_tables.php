<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flows')) {
            Schema::create('flows', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->string('trigger_event')->index();
                $table->boolean('is_active')->default(true);
                $table->string('status')->default('active'); // active, paused_error, draft
                $table->unsignedInteger('consecutive_errors')->default(0);
                $table->unsignedInteger('max_error_threshold')->default(3); // Paused after N errors (TEST ANCHOR)
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('flow_versions')) {
            Schema::create('flow_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('flow_id')->constrained('flows')->cascadeOnDelete();
                $table->unsignedInteger('version_number')->default(1);
                $table->jsonb('nodes');
                $table->text('plain_explanation')->nullable(); // Plain English flow explanation (TEST ANCHOR)
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('flow_runs')) {
            Schema::create('flow_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('flow_id')->constrained('flows')->cascadeOnDelete();
                $table->foreignId('flow_version_id')->constrained('flow_versions')->cascadeOnDelete();
                $table->jsonb('trigger_payload')->nullable();
                $table->string('status')->default('success'); // success, error, simulated
                $table->text('error_message')->nullable();
                $table->boolean('is_manual_retry')->default(false); // TEST ANCHOR
                $table->timestamps();
            });
        }

        $tables = ['flows', 'flow_versions', 'flow_runs'];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'business_id')) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
                DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");

                DB::statement(<<<SQL
                    CREATE POLICY tenant_isolation ON {$table}
                        USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                        WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                SQL);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_runs');
        Schema::dropIfExists('flow_versions');
        Schema::dropIfExists('flows');
    }
};
