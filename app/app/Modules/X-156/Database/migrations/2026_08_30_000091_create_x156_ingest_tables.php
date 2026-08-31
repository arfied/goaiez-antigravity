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
        if (! Schema::hasTable('ingest_sources')) {
            Schema::create('ingest_sources', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('source_type')->index(); // salesforce, hubspot, ghl, meta_lead_ad, drive, notion, hcp, jobber, qr_scan
                $table->string('source_name');
                $table->string('secret_key')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ingest_runs')) {
            Schema::create('ingest_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('source_id')->constrained('ingest_sources')->cascadeOnDelete();
                $table->unsignedInteger('records_ingested')->default(0);
                $table->string('attestation_id')->index(); // P-069: contact write carries attestation
                $table->string('status')->default('completed');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ingest_rejections')) {
            Schema::create('ingest_rejections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('source_id')->nullable()->index();
                $table->jsonb('raw_payload')->nullable();
                $table->string('rejection_reason');
                $table->boolean('signature_verified')->default(false);
                $table->timestamps();
            });
        }

        $tables = ['ingest_sources', 'ingest_runs', 'ingest_rejections'];

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
        Schema::dropIfExists('ingest_rejections');
        Schema::dropIfExists('ingest_runs');
        Schema::dropIfExists('ingest_sources');
    }
};
