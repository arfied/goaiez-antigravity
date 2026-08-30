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
        if (! Schema::hasTable('provider_roster')) {
            Schema::create('provider_roster', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('provider_name');
                $table->unsignedInteger('tier_level')->default(1); // 1 = low cost, 2 = expensive fallback
                $table->unsignedInteger('cost_per_lookup_cents')->default(5); // in cents (integer)
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('provider_attempts')) {
            Schema::create('provider_attempts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('request_id')->index();
                $table->foreignId('provider_id')->constrained('provider_roster')->cascadeOnDelete();
                $table->unsignedInteger('tier_level');
                $table->jsonb('returned_data')->nullable();
                $table->string('status')->default('success'); // success, rejected_junk, error
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('record_shapes')) {
            Schema::create('record_shapes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('entity_type');
                $table->jsonb('schema_definition');
                $table->timestamps();
            });
        }

        $tables = ['provider_roster', 'provider_attempts', 'record_shapes'];

        foreach ($tables as $table) {
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

    public function down(): void
    {
        Schema::dropIfExists('record_shapes');
        Schema::dropIfExists('provider_attempts');
        Schema::dropIfExists('provider_roster');
    }
};
