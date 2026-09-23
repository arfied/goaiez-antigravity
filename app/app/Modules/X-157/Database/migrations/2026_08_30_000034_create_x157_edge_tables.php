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
        if (! Schema::hasTable('edge_zones')) {
            Schema::create('edge_zones', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('domain_name')->index();
                $table->string('provider')->default('cloudflare'); // Cloudflare edge (§33.1, G6-33)
                $table->string('zone_id')->index();
                $table->boolean('has_valid_ssl')->default(false);
                $table->string('ssl_certificate_id')->nullable();
                $table->string('status')->default('active'); // active, pending_ssl, degraded
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('deployments')) {
            Schema::create('deployments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('edge_zone_id')->constrained('edge_zones')->cascadeOnDelete();
                $table->string('deploy_hash')->index();
                $table->string('status')->default('deployed'); // deploying, deployed, rolled_back, failed_ssl, superseded, unpublished
                $table->unsignedInteger('speed_score')->default(100);
                $table->unsignedInteger('speed_budget_ms')->default(1500); // 1.5s TTFB budget
                $table->unsignedInteger('measured_ttfb_ms')->default(120);
                $table->string('rollback_reason')->nullable();
                $table->timestamp('deployed_at')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['edge_zones', 'deployments'];

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
        Schema::dropIfExists('deployments');
        Schema::dropIfExists('edge_zones');
    }
};
