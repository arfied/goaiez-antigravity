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
        if (Schema::hasTable('forecasts')) {
            Schema::dropIfExists('forecasts');
        }

        Schema::create('forecasts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('period_month')->index();
            $table->bigInteger('booked_cents')->default(0); // Distinct from collected (G1-36, G1-46, G1-47)
            $table->bigInteger('collected_cents')->default(0); // Distinct from booked (never summed)
            $table->unsignedInteger('churn_risk_pct')->default(0);
            $table->boolean('is_high_risk')->default(false);
            $table->boolean('alert_created')->default(false); // Exactly one alert row on risk above threshold (TEST ANCHOR)
            $table->timestamps();
        });

        $tables = ['forecasts'];

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
        Schema::dropIfExists('forecasts');
    }
};
