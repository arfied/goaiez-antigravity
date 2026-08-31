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
        if (! Schema::hasTable('templates')) {
            Schema::create('templates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('industry_code')->index();
                $table->string('funnel_type')->default('quote'); // emergency, quote, book, webinar (G6-28, G6-30)
                $table->jsonb('design_tokens'); // palette, type scale, rhythm (TEST ANCHOR & G6-29)
                $table->decimal('conversion_score', 4, 2)->default(0.00);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('template_blocks')) {
            Schema::create('template_blocks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
                $table->string('render_source_module'); // TEST ANCHOR: every block names source module
                $table->string('render_source_block'); // TEST ANCHOR: every block names manifest @renders
                $table->unsignedSmallInteger('order_index')->default(0);
                $table->jsonb('config')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('funnel_shapes')) {
            Schema::create('funnel_shapes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('shape_key')->index();
                $table->jsonb('steps_flow');
                $table->timestamps();
            });
        }

        $tables = ['templates', 'template_blocks', 'funnel_shapes'];

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
        Schema::dropIfExists('funnel_shapes');
        Schema::dropIfExists('template_blocks');
        Schema::dropIfExists('templates');
    }
};
