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
        if (! Schema::hasTable('content_plans')) {
            Schema::create('content_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('week_label')->index();
                $table->unsignedSmallInteger('posts_per_week_cadence')->default(3); // G5-49, G16-16: approve cadence
                $table->boolean('is_cadence_approved')->default(false); // TEST ANCHOR: <=3 taps approval
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('plan_items')) {
            Schema::create('plan_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('plan_id')->constrained('content_plans')->cascadeOnDelete();
                $table->string('channel'); // facebook, instagram, gbp, email
                $table->string('topic_theme');
                $table->string('source_event'); // TEST ANCHOR: every plan item names its source event
                $table->date('scheduled_date');
                $table->boolean('is_scheduled')->default(false);
                $table->timestamps();
            });
        }

        $tables = ['content_plans', 'plan_items'];

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
        Schema::dropIfExists('plan_items');
        Schema::dropIfExists('content_plans');
    }
};
