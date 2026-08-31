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
        if (! Schema::hasTable('job_costs')) {
            Schema::create('job_costs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('job_id')->index();
                $table->string('price_book_version'); // TEST ANCHOR: every cost row cites the pricebook version
                $table->bigInteger('labor_cost_cents')->default(0);
                $table->bigInteger('materials_cost_cents')->default(0);
                $table->bigInteger('overhead_cost_cents')->default(0);
                $table->bigInteger('total_cost_cents')->default(0);
                $table->bigInteger('revenue_cents')->default(0);
                $table->bigInteger('gross_margin_cents')->default(0);
                $table->decimal('gross_margin_pct', 5, 2)->default(0.0);
                $table->unsignedBigInteger('tech_id')->nullable()->index();
                $table->string('service_type')->default('general');
                $table->string('source')->default('inbound_call');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('margin_snapshots')) {
            Schema::create('margin_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('period'); // daily, weekly, monthly
                $table->bigInteger('total_revenue_cents')->default(0);
                $table->bigInteger('total_cost_cents')->default(0);
                $table->bigInteger('gross_margin_cents')->default(0);
                $table->decimal('margin_pct', 5, 2)->default(0.0);
                $table->timestamps();
            });
        }

        $tables = ['job_costs', 'margin_snapshots'];

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
        Schema::dropIfExists('margin_snapshots');
        Schema::dropIfExists('job_costs');
    }
};
