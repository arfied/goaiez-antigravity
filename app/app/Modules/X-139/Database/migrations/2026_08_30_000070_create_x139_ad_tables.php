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
        if (! Schema::hasTable('ad_connections')) {
            Schema::create('ad_connections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('platform'); // google_ads, meta_capi
                $table->string('account_id');
                $table->boolean('is_connected')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('conversion_uploads')) {
            Schema::create('conversion_uploads', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('job_id')->index();
                $table->bigInteger('conversion_value_cents');
                $table->string('gclid_or_fbc')->nullable();
                $table->string('status')->default('uploaded'); // uploaded, rejected
                $table->string('rejection_reason')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['ad_connections', 'conversion_uploads'];

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
        Schema::dropIfExists('conversion_uploads');
        Schema::dropIfExists('ad_connections');
    }
};
