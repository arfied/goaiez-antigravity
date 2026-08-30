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
        if (! Schema::hasTable('demo_tenants')) {
            Schema::create('demo_tenants', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('demo_slug')->index();
                $table->string('prospect_domain');
                $table->boolean('is_mock')->default(true); // G2-69, G6-01, G6-10: is_mock on every row
                $table->boolean('is_converted')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('demo_sessions')) {
            Schema::create('demo_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('demo_tenant_id')->constrained('demo_tenants')->cascadeOnDelete();
                $table->string('session_token')->index();
                $table->string('transport_type')->default('sandbox'); // TEST ANCHOR: sandbox transport by type
                $table->boolean('is_mock')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('demo_ledger')) {
            Schema::create('demo_ledger', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('demo_tenant_id')->constrained('demo_tenants')->cascadeOnDelete();
                $table->string('entry_type'); // credit, debit
                $table->integer('amount_cents');
                $table->string('description');
                $table->boolean('is_mock')->default(true); // G9-12
                $table->timestamps();
            });
        }

        $tables = ['demo_tenants', 'demo_sessions', 'demo_ledger'];

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
        Schema::dropIfExists('demo_ledger');
        Schema::dropIfExists('demo_sessions');
        Schema::dropIfExists('demo_tenants');
    }
};
