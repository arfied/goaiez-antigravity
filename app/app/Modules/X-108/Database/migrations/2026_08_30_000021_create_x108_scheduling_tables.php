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
        if (! Schema::hasTable('resources')) {
            Schema::create('resources', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->string('calendar_type')->default('google'); // google, outlook, internal
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('availability_rules')) {
            Schema::create('availability_rules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('resource_id')->nullable()->constrained('resources')->nullOnDelete();
                $table->unsignedSmallInteger('day_of_week')->default(1); // 1 = Monday, 7 = Sunday
                $table->time('start_time')->default('09:00');
                $table->time('end_time')->default('17:00');
                $table->boolean('is_blackout')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('slot_locks')) {
            Schema::create('slot_locks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->timestamp('slot_start');
                $table->timestamp('slot_end');
                $table->string('locked_for_session')->index();
                $table->timestamp('expires_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('appointments')) {
            Schema::create('appointments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('resource_id')->nullable()->constrained('resources')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('people')->nullOnDelete();
                $table->string('service_name');
                $table->timestamp('start_time');
                $table->timestamp('end_time');
                $table->boolean('is_member')->default(false);
                $table->string('status')->default('booked'); // booked, reminded, cancelled, completed
                $table->string('conference_link')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('waitlists')) {
            Schema::create('waitlists', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('customer_name');
                $table->string('customer_phone');
                $table->string('service_name');
                $table->date('preferred_date');
                $table->boolean('is_member')->default(false);
                $table->string('status')->default('pending'); // pending, offered, fulfilled
                $table->timestamps();
            });
        }

        $tables = ['resources', 'availability_rules', 'slot_locks', 'appointments', 'waitlists'];

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
        Schema::dropIfExists('waitlists');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('slot_locks');
        Schema::dropIfExists('availability_rules');
        Schema::dropIfExists('resources');
    }
};
