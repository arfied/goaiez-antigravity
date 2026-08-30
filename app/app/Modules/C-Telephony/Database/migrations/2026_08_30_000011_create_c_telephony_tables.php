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
        if (! Schema::hasTable('carrier_roster')) {
            Schema::create('carrier_roster', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('carrier_name');
                $table->string('adapter_class');
                $table->boolean('is_active')->default(true);
                $table->boolean('supports_rcs')->default(false);
                $table->boolean('supports_voice')->default(true);
                $table->bigInteger('cost_per_msg_cents')->default(1);
                $table->bigInteger('cost_per_min_cents')->default(2);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('carrier_bindings')) {
            Schema::create('carrier_bindings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('thread_key')->index();
                $table->string('carrier_name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('carrier_health')) {
            Schema::create('carrier_health', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('carrier_name');
                $table->string('status')->default('healthy'); // healthy, cold, degraded, down
                $table->unsignedInteger('latency_ms')->default(150);
                $table->unsignedInteger('error_rate_pct')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('carrier_receipts')) {
            Schema::create('carrier_receipts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('message_id')->nullable()->index();
                $table->string('carrier_name');
                $table->string('carrier_message_id')->nullable();
                $table->string('status')->default('delivered');
                $table->string('shaken_stir_grade')->nullable(); // A, B, C
                $table->bigInteger('cost_cents')->default(0);
                $table->timestamps();
            });
        }

        $tables = ['carrier_roster', 'carrier_bindings', 'carrier_health', 'carrier_receipts'];

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
        Schema::dropIfExists('carrier_receipts');
        Schema::dropIfExists('carrier_health');
        Schema::dropIfExists('carrier_bindings');
        Schema::dropIfExists('carrier_roster');
    }
};
