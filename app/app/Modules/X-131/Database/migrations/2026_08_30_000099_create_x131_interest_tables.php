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
        if (! Schema::hasTable('interest_clusters')) {
            Schema::create('interest_clusters', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('cluster_name')->index();
                $table->string('category')->default('services');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('person_interests')) {
            Schema::create('person_interests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('person_id')->index();
                $table->string('topic')->index();
                $table->decimal('confidence_rate', 4, 3)->default(1.000); // TEST ANCHOR: confidence
                $table->string('source')->default('inferred_web_interaction'); // TEST ANCHOR: source
                $table->boolean('is_tenant_set')->default(false); // TEST ANCHOR: tenant-set never overwritten
                $table->timestamps();

                $table->unique(['business_id', 'person_id', 'topic']);
            });
        }

        $tables = ['interest_clusters', 'person_interests'];

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
        Schema::dropIfExists('person_interests');
        Schema::dropIfExists('interest_clusters');
    }
};
