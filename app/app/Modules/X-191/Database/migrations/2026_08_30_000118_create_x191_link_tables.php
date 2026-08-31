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
        if (! Schema::hasTable('link_targets')) {
            Schema::create('link_targets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('domain')->index();
                $table->string('target_url');
                $table->boolean('is_pbn')->default(false); // TEST ANCHOR: flagged PBN never receives pitch
                $table->unsignedSmallInteger('da_score')->default(30);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('link_pitches')) {
            Schema::create('link_pitches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('target_id')->constrained('link_targets')->cascadeOnDelete();
                $table->text('pitch_body');
                $table->string('page_specific_fact')->nullable(); // TEST ANCHOR: no fact fails qualify gate (G11-34)
                $table->unsignedSmallInteger('follow_up_count')->default(0); // G8-20, G12-07: max 1 follow-up
                $table->boolean('is_sent')->default(false);
                $table->string('sent_month')->index(); // TEST ANCHOR: monthly ceiling enforcement
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('link_placements')) {
            Schema::create('link_placements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('pitch_id')->nullable()->constrained('link_pitches')->nullOnDelete();
                $table->string('placed_url');
                $table->string('anchor_text');
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_monitored_at')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['link_targets', 'link_pitches', 'link_placements'];

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
        Schema::dropIfExists('link_placements');
        Schema::dropIfExists('link_pitches');
        Schema::dropIfExists('link_targets');
    }
};
