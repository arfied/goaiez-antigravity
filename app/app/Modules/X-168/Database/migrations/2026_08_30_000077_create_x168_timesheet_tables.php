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
        if (! Schema::hasTable('pay_rules')) {
            Schema::create('pay_rules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('rule_name');
                $table->decimal('standard_hours_per_week', 5, 2)->default(40.00);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('timesheets')) {
            Schema::create('timesheets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('person_id')->index();
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('total_hours', 8, 2)->default(0.00);
                $table->string('status')->default('open'); // open, submitted, approved
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('timesheet_entries')) {
            Schema::create('timesheet_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('timesheet_id')->constrained('timesheets')->cascadeOnDelete();
                $table->unsignedBigInteger('job_id')->nullable()->index();
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->unsignedInteger('duration_minutes')->default(0);
                $table->string('state_window'); // en_route, on_site
                $table->decimal('location_lat', 10, 7)->nullable();
                $table->decimal('location_lng', 10, 7)->nullable();
                $table->timestamps();
            });
        }

        $tables = ['pay_rules', 'timesheets', 'timesheet_entries'];

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
        Schema::dropIfExists('timesheet_entries');
        Schema::dropIfExists('timesheets');
        Schema::dropIfExists('pay_rules');
    }
};
