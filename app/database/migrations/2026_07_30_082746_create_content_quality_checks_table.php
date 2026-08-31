<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The quality gate a growth page must pass before publishing
 * (DATA-MODEL §5.11): uniqueness, first-party data, demand evidence,
 * readability. Failing rows keep their reasons so the gate is explainable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_quality_checks', function (Blueprint $table): void {
            $table->id();

            // DATA-MODEL keys this by page_id alone; business_id is added
            // because the RLS policy compares it (same rationale as reviews).
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Unconstrained: growth_pages belongs to the content slice, which
            // is not part of C/D/F. The foreign key lands with that table —
            // the same deferral reviews.customer_id used within this stack.
            $table->unsignedBigInteger('page_id');

            $table->smallInteger('uniqueness_score')->nullable();
            $table->smallInteger('first_party_data_count')->nullable();
            $table->jsonb('demand_evidence')->nullable();
            $table->smallInteger('readability_score')->nullable();

            $table->boolean('passed')->nullable();

            // TEXT[] in DATA-MODEL; jsonb list here.
            $table->jsonb('failure_reasons')->nullable();

            // The datum — this table has no other timestamps.
            $table->timestamp('checked_at')->nullable();

            $table->index('business_id');
            $table->index('page_id');
        });

        DB::statement('ALTER TABLE content_quality_checks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE content_quality_checks FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON content_quality_checks
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('content_quality_checks');
    }
};
