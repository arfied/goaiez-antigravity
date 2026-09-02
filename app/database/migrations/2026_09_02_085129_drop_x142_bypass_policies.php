<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP POLICY IF EXISTS mcp_tokens_bypass_policy ON mcp_tokens');
        DB::statement('DROP POLICY IF EXISTS webhook_subscriptions_bypass_policy ON webhook_subscriptions');
    }

    public function down(): void
    {
        // Re-adding bypass policies is not supported as they were a security risk.
    }
};
