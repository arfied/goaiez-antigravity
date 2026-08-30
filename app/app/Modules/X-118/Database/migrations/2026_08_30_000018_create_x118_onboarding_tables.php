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
        if (! Schema::hasTable('onboarding_runs')) {
            Schema::create('onboarding_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('business_name');
                $table->string('contact_phone');
                $table->string('provisioned_number')->nullable();
                $table->string('status')->default('started'); // started, confirmed, live, completed
                $table->unsignedInteger('ttfm_ms')->default(1200);
                $table->unsignedInteger('asked_fields_count')->default(2);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('onboarding_steps')) {
            Schema::create('onboarding_steps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('run_id')->constrained('onboarding_runs')->cascadeOnDelete();
                $table->string('step_name');
                $table->boolean('is_hard_stop')->default(false);
                $table->string('status')->default('completed'); // pending, completed, skipped
                $table->timestamps();
            });
        }

        $tables = ['onboarding_runs', 'onboarding_steps'];

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
        Schema::dropIfExists('onboarding_steps');
        Schema::dropIfExists('onboarding_runs');
    }
};
