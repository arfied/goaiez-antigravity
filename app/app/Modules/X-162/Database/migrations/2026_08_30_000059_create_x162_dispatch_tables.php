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
        if (! Schema::hasTable('dispatch_assignments')) {
            Schema::create('dispatch_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('job_id')->index();
                $table->unsignedBigInteger('tech_id')->index();
                $table->string('status')->default('dispatched'); // dispatched, en_route, on_site, completed
                $table->timestamp('en_route_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('routes')) {
            Schema::create('routes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('tech_id')->index();
                $table->jsonb('stop_order');
                $table->decimal('total_distance_km', 6, 2)->default(0.00);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('eta_predictions')) {
            Schema::create('eta_predictions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('job_id')->index();
                $table->timestamp('estimated_arrival_at');
                $table->unsignedInteger('eta_minutes');
                $table->timestamp('notification_sent_at')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['dispatch_assignments', 'routes', 'eta_predictions'];

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
        Schema::dropIfExists('eta_predictions');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('dispatch_assignments');
    }
};
