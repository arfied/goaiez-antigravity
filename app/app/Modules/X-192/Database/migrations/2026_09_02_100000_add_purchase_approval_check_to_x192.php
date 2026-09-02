<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add check constraint so no membership is purchased without an approval action row
        DB::statement('ALTER TABLE directory_memberships ADD CONSTRAINT check_purchase_approval CHECK (is_purchased = false OR approved_by_action_id IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE directory_memberships DROP CONSTRAINT check_purchase_approval');
    }
};
