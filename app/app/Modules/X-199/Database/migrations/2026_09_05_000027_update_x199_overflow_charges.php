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
        Schema::table('overflow_charges', function (Blueprint $table): void {
            $table->string('card_token')->nullable()->change();
            $table->string('reference_id')->nullable()->change();
            $table->string('status')->default('charged');
        });

        DB::statement('ALTER TABLE overflow_charges ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE overflow_charges FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON overflow_charges');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON overflow_charges
                USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::table('overflow_charges', function (Blueprint $table): void {
            $table->dropColumn('status');
            $table->string('card_token')->nullable(false)->change();
            $table->string('reference_id')->nullable(false)->change();
        });
    }
};
