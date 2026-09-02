<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mcp_tokens')) {
            Schema::create('mcp_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->string('token_hash', 64)->unique();
                $table->string('role_scope')->default('staff');
                $table->json('abilities')->nullable();
                $table->boolean('is_revoked')->default(false);
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });

            DB::statement('ALTER TABLE mcp_tokens ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE mcp_tokens FORCE ROW LEVEL SECURITY');
            DB::statement("
                CREATE POLICY mcp_tokens_tenant_policy ON mcp_tokens
                FOR ALL
                TO PUBLIC
                USING (business_id = NULLIF(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = NULLIF(current_setting('app.business_id', true), '')::bigint)
            ");
            DB::statement('CREATE POLICY mcp_tokens_bypass_policy ON mcp_tokens FOR ALL TO goaiez_app USING (current_setting(\'app.bypass_rls\', true) = \'on\')');
        }

        if (!Schema::hasTable('webhook_subscriptions')) {
            Schema::create('webhook_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('url');
                $table->json('events');
                $table->string('secret')->nullable();
                $table->timestamps();
            });

            DB::statement('ALTER TABLE webhook_subscriptions ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE webhook_subscriptions FORCE ROW LEVEL SECURITY');
            DB::statement("
                CREATE POLICY webhook_subscriptions_tenant_policy ON webhook_subscriptions
                FOR ALL
                TO PUBLIC
                USING (business_id = NULLIF(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = NULLIF(current_setting('app.business_id', true), '')::bigint)
            ");
            DB::statement('CREATE POLICY webhook_subscriptions_bypass_policy ON webhook_subscriptions FOR ALL TO goaiez_app USING (current_setting(\'app.bypass_rls\', true) = \'on\')');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
        Schema::dropIfExists('mcp_tokens');
    }
};
