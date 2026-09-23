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
        if (! Schema::hasTable('agencies')) {
            Schema::create('agencies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('agency_name');
                $table->string('whitelabel_domain')->nullable();
                $table->string('agency_mode')->default('full_service'); // full_service, co_managed, self_service (§91)
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('agency_clients')) {
            Schema::create('agency_clients', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
                $table->foreignId('client_business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('client_name');
                $table->string('status')->default('active'); // active, paused, churned
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('markups')) {
            Schema::create('markups', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
                $table->string('service_type'); // voice_minute, sms_segment, ai_call
                $table->bigInteger('wholesale_rate_cents');
                $table->bigInteger('retail_markup_cents');
                $table->bigInteger('retail_rate_cents');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('impersonation_log')) {
            Schema::create('impersonation_log', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('target_client_business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('reason');
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('ended_at')->nullable();
            });
        }

        if (! Schema::hasTable('staff_roles')) {
            Schema::create('staff_roles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('agency_id')->constrained('agencies')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role')->default('account_manager'); // owner, account_manager, support, billing
                $table->boolean('is_active')->default(true);
                $table->timestamp('deactivated_at')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['agencies', 'agency_clients', 'markups', 'impersonation_log', 'staff_roles'];

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
            DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$table} TO goaiez_app");
            try {
                DB::statement("GRANT USAGE, SELECT ON SEQUENCE {$table}_id_seq TO goaiez_app");
            } catch (Exception $e) {
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_roles');
        Schema::dropIfExists('impersonation_log');
        Schema::dropIfExists('markups');
        Schema::dropIfExists('agency_clients');
        Schema::dropIfExists('agencies');
    }
};
