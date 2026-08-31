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
        if (! Schema::hasTable('enrichment_runs')) {
            Schema::create('enrichment_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('domain')->index();
                $table->string('status')->default('completed');
                $table->timestamp('fetched_at')->nullable(); // P-143: staleness is fetched_at, never eviction (G3-08)
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('enrichment_fields')) {
            Schema::create('enrichment_fields', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('run_id')->nullable()->constrained('enrichment_runs')->nullOnDelete();
                $table->string('entity_id')->index();
                $table->string('field_key')->index();
                $table->text('field_value');
                $table->string('source'); // wappalyzer, clearbit, dns_mx, meta_pixel
                $table->decimal('confidence_score', 4, 3);
                $table->boolean('is_usable')->default(true); // TEST ANCHOR: confidence < threshold never in template
                $table->timestamps();
            });
        }

        $tables = ['enrichment_runs', 'enrichment_fields'];

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
        Schema::dropIfExists('enrichment_fields');
        Schema::dropIfExists('enrichment_runs');
    }
};
