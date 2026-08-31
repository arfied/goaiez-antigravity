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
        if (! Schema::hasTable('extension_sessions')) {
            Schema::create('extension_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('session_token')->unique();
                $table->boolean('is_authenticated_scrape')->default(false); // G3-05: authenticated=false default (P-078)
                $table->boolean('is_active')->default(true);
                $table->boolean('is_aborted')->default(false); // TEST ANCHOR: rate limit banner aborts session
                $table->string('abort_reason')->nullable();
                $table->unsignedInteger('actions_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('extension_injections')) {
            Schema::create('extension_injections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('session_id')->constrained('extension_sessions')->cascadeOnDelete();
                $table->string('source_url');
                $table->string('attestation_id'); // G2-03: write carries attestation_id (P-069)
                $table->jsonb('prospect_payload');
                $table->timestamps();
            });
        }

        $tables = ['extension_sessions', 'extension_injections'];

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
        Schema::dropIfExists('extension_injections');
        Schema::dropIfExists('extension_sessions');
    }
};
