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
        if (! Schema::hasTable('mcp_tokens')) {
            Schema::create('mcp_tokens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('token_hash')->index();
                $table->string('token_name');
                $table->string('role_scope')->default('staff'); // staff, owner (G4-18)
                $table->jsonb('permissions')->nullable();
                $table->boolean('is_revoked')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('webhook_subscriptions')) {
            Schema::create('webhook_subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('target_url');
                $table->string('event_filter')->index();
                $table->string('secret');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $tables = ['mcp_tokens', 'webhook_subscriptions'];

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
        Schema::dropIfExists('webhook_subscriptions');
        Schema::dropIfExists('mcp_tokens');
    }
};
