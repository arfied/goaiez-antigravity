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
        if (! Schema::hasTable('number_pool')) {
            Schema::create('number_pool', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('phone_number')->index();
                $table->string('area_code', 10);
                $table->string('carrier_name')->default('telnyx');
                $table->string('status')->default('available'); // available, assigned, parked, released
                $table->unsignedInteger('complaint_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('number_assignments')) {
            Schema::create('number_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('phone_number_id')->constrained('number_pool')->cascadeOnDelete();
                $table->timestamp('assigned_at')->useCurrent();
                $table->string('status')->default('active'); // active, released, migrating
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('brand_registrations')) {
            Schema::create('brand_registrations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('brand_name');
                $table->string('tcr_brand_id')->nullable();
                $table->string('registration_status')->default('pending'); // pending, approved, active
                $table->string('brand_type')->default('shared'); // shared, dedicated
                $table->timestamps();
            });
        } else {
            Schema::table('brand_registrations', function (Blueprint $table): void {
                if (! Schema::hasColumn('brand_registrations', 'brand_name')) {
                    $table->string('brand_name')->nullable();
                }
                if (! Schema::hasColumn('brand_registrations', 'tcr_brand_id')) {
                    $table->string('tcr_brand_id')->nullable();
                }
                if (! Schema::hasColumn('brand_registrations', 'registration_status')) {
                    $table->string('registration_status')->default('pending');
                }
                if (! Schema::hasColumn('brand_registrations', 'brand_type')) {
                    $table->string('brand_type')->default('shared');
                }
            });
        }

        if (! Schema::hasTable('number_parks')) {
            Schema::create('number_parks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('phone_number_id')->constrained('number_pool')->cascadeOnDelete();
                $table->timestamp('parked_at')->useCurrent();
                $table->timestamp('park_until')->nullable();
                $table->boolean('is_released')->default(false);
                $table->timestamps();
            });
        }

        $tables = ['number_pool', 'number_assignments', 'brand_registrations', 'number_parks'];

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
        Schema::dropIfExists('number_parks');
        Schema::dropIfExists('brand_registrations');
        Schema::dropIfExists('number_assignments');
        Schema::dropIfExists('number_pool');
    }
};
