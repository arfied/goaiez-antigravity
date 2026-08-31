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
        if (! Schema::hasTable('tenant_zero_config')) {
            Schema::create('tenant_zero_config', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->boolean('is_tenant_zero')->default(false);
                $table->boolean('public_proof_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('published_metrics')) {
            Schema::create('published_metrics', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('metric_key')->index();
                $table->string('published_value')->nullable(); // Pulled automatically if drifted (TEST ANCHOR)
                $table->text('live_query');
                $table->string('last_verified_value')->nullable();
                $table->string('status')->default('published'); // published, verified, pulled_drifted
                $table->timestamps();
            });
        }

        $tables = ['tenant_zero_config', 'published_metrics'];

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
        Schema::dropIfExists('published_metrics');
        Schema::dropIfExists('tenant_zero_config');
    }
};
