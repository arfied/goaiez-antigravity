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
        if (! Schema::hasTable('attribution_queries')) {
            Schema::create('attribution_queries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('job_id')->nullable()->index();
                $table->unsignedBigInteger('job_value')->nullable(); // null until estimate closed (TEST ANCHOR)
                $table->jsonb('touches'); // array of touchpoints
                $table->string('attribution_status')->default('none'); // single, ambiguous, none
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('roi_snapshots')) {
            Schema::create('roi_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('campaign_name');
                $table->bigInteger('ad_spend_cents')->default(0);
                $table->bigInteger('closed_revenue_cents')->default(0);
                $table->timestamps();
            });
        }

        $tables = ['attribution_queries', 'roi_snapshots'];

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
        Schema::dropIfExists('roi_snapshots');
        Schema::dropIfExists('attribution_queries');
    }
};
