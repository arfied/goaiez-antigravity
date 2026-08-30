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
        if (! Schema::hasTable('design_changes')) {
            Schema::create('design_changes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('page_id')->index();
                $table->string('change_type'); // color_token, typography, block_order, block_add, form_gen
                $table->string('block_ref')->index(); // block references only, no generated markup (TEST ANCHOR)
                $table->jsonb('previous_state')->nullable();
                $table->jsonb('new_state');
                $table->decimal('contrast_ratio', 4, 2)->default(7.0); // 4.5:1 minimum WCAG (TEST ANCHOR)
                $table->string('status')->default('applied'); // applied, undone, refused_contrast
                $table->timestamps();
            });
        }

        $tables = ['design_changes'];

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
        Schema::dropIfExists('design_changes');
    }
};
