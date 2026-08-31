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
        if (! Schema::hasTable('accounting_connections')) {
            Schema::create('accounting_connections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('provider'); // quickbooks, xero
                $table->string('realm_id')->nullable();
                $table->text('access_token')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('account_mappings')) {
            Schema::create('account_mappings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('connection_id')->constrained('accounting_connections')->cascadeOnDelete();
                $table->string('internal_category')->index();
                $table->string('remote_gl_account_id');
                $table->string('remote_gl_account_name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sync_runs')) {
            Schema::create('sync_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('connection_id')->constrained('accounting_connections')->cascadeOnDelete();
                $table->unsignedInteger('records_synced')->default(0);
                $table->unsignedInteger('conflicts_count')->default(0);
                $table->string('status')->default('completed');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('accounting_sync_conflicts')) {
            Schema::create('accounting_sync_conflicts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('sync_run_id')->nullable()->constrained('sync_runs')->nullOnDelete();
                $table->string('transaction_ref')->index();
                $table->decimal('confidence_score', 4, 3);
                $table->string('assigned_category')->default('uncategorised'); // TEST ANCHOR
                $table->boolean('flagged_for_review')->default(true);
                $table->string('status')->default('open'); // Never auto-closed (TEST ANCHOR)
                $table->timestamps();
            });
        }

        $tables = ['accounting_connections', 'account_mappings', 'sync_runs', 'accounting_sync_conflicts'];

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
        Schema::dropIfExists('accounting_sync_conflicts');
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('account_mappings');
        Schema::dropIfExists('accounting_connections');
    }
};
