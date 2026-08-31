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
        if (! Schema::hasTable('rates')) {
            Schema::create('rates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('rate_code')->index();
                $table->string('currency')->default('USD');
                $table->unsignedInteger('amount_cents');
                $table->unsignedInteger('current_version')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rate_versions')) {
            Schema::create('rate_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('rate_id')->constrained('rates')->cascadeOnDelete();
                $table->unsignedInteger('version_number');
                $table->unsignedInteger('amount_cents');
                $table->unsignedBigInteger('tenant_locked_business_id')->nullable()->index(); // Grandfathered lock (G17-10)
                $table->timestamp('effective_from');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('allowances')) {
            Schema::create('allowances', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('allowance_code')->index();
                $table->unsignedInteger('units_granted')->default(0);
                $table->unsignedInteger('units_used')->default(0);
                $table->string('policy')->default('rollover'); // rollover, expire (G17-26)
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['rates', 'rate_versions', 'allowances'];

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
        Schema::dropIfExists('allowances');
        Schema::dropIfExists('rate_versions');
        Schema::dropIfExists('rates');
    }
};
