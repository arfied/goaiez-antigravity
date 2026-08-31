<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The hosted review hub, one page per location (DATA-MODEL §5.11).
 *
 * Public queries against it always filter status IN (approved, displayed)
 * (§5.14) — enforced where the query is written, recorded here so nobody
 * "optimizes" the filter away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_hub_pages', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('slug');
            $table->string('title')->nullable();
            $table->text('intro_content')->nullable();

            $table->boolean('is_published')->default(true);
            $table->timestamp('last_generated_at')->nullable();

            $table->timestamps();

            // Not in DATA-MODEL, added deliberately: a slug is a lookup key,
            // and two locations of one business sharing one is routing
            // ambiguity. Composite with business_id (never global) so two
            // tenants can both be /best-hvac.
            $table->unique(['business_id', 'slug']);
        });

        DB::statement('ALTER TABLE review_hub_pages ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE review_hub_pages FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON review_hub_pages
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('review_hub_pages');
    }
};
