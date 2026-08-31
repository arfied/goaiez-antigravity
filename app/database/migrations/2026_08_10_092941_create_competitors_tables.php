<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `28` §5.5 competitor set + snapshots — Places-sourced, tenant-owned.
 *
 * Row 15 slice 2. Names are stored for the Advanced table; the Normal surface
 * never prints them (decision 196). Snapshots are append-friendly facts about
 * a competitor at a point in time; the comparison reader uses the latest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('place_id');
            // Stored for Advanced / Ops. Never rendered on Normal (196).
            $table->string('name');
            // auto = proposed from Places nearby; owner = confirmed later.
            $table->string('source');
            $table->boolean('confirmed')->default(false);
            $table->timestamps();

            $table->unique(['location_id', 'place_id']);
            $table->index(['business_id', 'location_id']);
        });

        Schema::create('competitor_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('review_count');
            $table->decimal('rating', 2, 1)->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['competitor_id', 'captured_at']);
            $table->index(['business_id', 'captured_at']);
        });

        foreach (['competitors', 'competitor_snapshots'] as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$table}
                    USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                    WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
            SQL);
        }

        DB::statement(<<<'SQL'
            ALTER TABLE competitors
                ADD CONSTRAINT competitors_source_is_known
                CHECK (source IN ('auto', 'owner'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_snapshots');
        Schema::dropIfExists('competitors');
    }
};
