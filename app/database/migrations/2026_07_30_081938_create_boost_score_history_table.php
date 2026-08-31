<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Boost Score over time, per location (DATA-MODEL §5.12). recorded_at is the
 * datum — the table has no other timestamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boost_score_history', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            $table->integer('score');

            $table->timestamp('recorded_at');

            $table->index('business_id');
        });

        // DATA-MODEL: CHECK (score BETWEEN 0 AND 100).
        DB::statement(
            'ALTER TABLE boost_score_history
                 ADD CONSTRAINT boost_score_between_0_and_100
                 CHECK (score BETWEEN 0 AND 100)'
        );

        // §5.13 idx_boost_history_location; raw for the DESC.
        DB::statement(
            'CREATE INDEX idx_boost_history_location
                 ON boost_score_history (location_id, recorded_at DESC)'
        );

        DB::statement('ALTER TABLE boost_score_history ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE boost_score_history FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON boost_score_history
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('boost_score_history');
    }
};
