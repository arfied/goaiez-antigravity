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
        if (! Schema::hasTable('membership_plans')) {
            Schema::create('membership_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->bigInteger('price_cents')->default(19900);
                $table->unsignedInteger('billing_interval_months')->default(12);
                $table->unsignedInteger('renewal_reminder_days')->default(7); // Configured reminder days (TEST ANCHOR)
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('memberships')) {
            Schema::create('memberships', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('plan_id')->constrained('membership_plans')->cascadeOnDelete();
                $table->unsignedBigInteger('person_id')->index();
                $table->string('status')->default('active'); // active, expired, renewed
                $table->timestamp('starts_at');
                $table->timestamp('renews_at');
                $table->timestamp('renewal_reminder_sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('member_visits')) {
            Schema::create('member_visits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('membership_id')->constrained('memberships')->cascadeOnDelete();
                $table->string('visit_type')->default('annual_tuneup');
                $table->timestamp('used_at')->nullable();
                $table->boolean('rolled_over')->default(false);
                $table->timestamps();
            });
        }

        $tables = ['membership_plans', 'memberships', 'member_visits'];

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
        Schema::dropIfExists('member_visits');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('membership_plans');
    }
};
