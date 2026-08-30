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
        if (! Schema::hasTable('schema_snapshots')) {
            Schema::create('schema_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('page_id')->index();
                $table->string('entity_type')->default('LocalBusiness'); // G12-03
                $table->jsonb('json_ld'); // Valid schema.org JSON-LD (G8-32)
                $table->string('commit_id')->index(); // TEST ANCHOR: same commit as Fact
                $table->boolean('is_valid_schema')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crawler_visits')) {
            Schema::create('crawler_visits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('page_id')->index();
                $table->string('bot_user_agent');
                $table->string('ip_address')->nullable();
                $table->timestamp('visited_at')->useCurrent();
            });
        }

        $tables = ['schema_snapshots', 'crawler_visits'];

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
        Schema::dropIfExists('crawler_visits');
        Schema::dropIfExists('schema_snapshots');
    }
};
