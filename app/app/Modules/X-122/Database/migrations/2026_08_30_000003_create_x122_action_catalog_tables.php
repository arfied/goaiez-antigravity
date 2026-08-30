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
        if (! Schema::hasTable('action_manifests')) {
            Schema::create('action_manifests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('action_name');
                $table->jsonb('schema');
                $table->string('reversal_action')->nullable();
                $table->boolean('is_reversible')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('action_invocations')) {
            Schema::create('action_invocations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('ref_id')->nullable()->index();
                $table->string('action_name');
                $table->jsonb('parameters');
                $table->jsonb('result')->nullable();
                $table->string('actor_type')->default('assistant');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('geo_country')->nullable();
                $table->string('geo_city')->nullable();
                $table->string('status')->default('completed');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('action_reversals')) {
            Schema::create('action_reversals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('invocation_id')->constrained('action_invocations')->cascadeOnDelete();
                $table->string('reversal_action_name');
                $table->jsonb('parameters')->nullable();
                $table->string('reversed_by')->default('system');
                $table->timestamp('reversed_at')->useCurrent();
                $table->timestamps();
            });
        }

        $tables = ['action_manifests', 'action_invocations', 'action_reversals'];

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
        Schema::dropIfExists('action_reversals');
        Schema::dropIfExists('action_invocations');
        Schema::dropIfExists('action_manifests');
    }
};
