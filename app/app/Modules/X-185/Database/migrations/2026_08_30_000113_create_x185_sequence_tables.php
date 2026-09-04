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
        if (! Schema::hasTable('sequences')) {
            Schema::create('sequences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->jsonb('frozen_elements')->nullable(); // TEST ANCHOR: element with design.changed in frozen_elements
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sequence_steps')) {
            Schema::create('sequence_steps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('sequence_id')->constrained('sequences')->cascadeOnDelete();
                $table->unsignedSmallInteger('step_number');
                $table->string('channel'); // email, sms
                $table->string('template_variant');
                $table->unsignedInteger('delay_hours')->default(24);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('content_packs')) {
        } else {
            Schema::table('content_packs', function (Blueprint $table): void {
                if (! Schema::hasColumn('content_packs', 'label_text')) {
                    $table->string('label_text')->nullable();
                }
                if (! Schema::hasColumn('content_packs', 'fleet_sample_size')) {
                    $table->unsignedInteger('fleet_sample_size')->default(0);
                }
                if (! Schema::hasColumn('content_packs', 'is_promoted')) {
                    $table->boolean('is_promoted')->default(false);
                }
            });
        }

        $tables = ['sequences', 'sequence_steps', 'content_packs'];

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
        Schema::dropIfExists('sequence_steps');
        Schema::dropIfExists('sequences');
    }
};
