<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP POLICY IF EXISTS link_lookup ON portal_links');
        DB::statement('
            CREATE POLICY link_lookup ON portal_links
                FOR SELECT
                USING (is_active = true)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS link_lookup ON portal_links');
    }
};
