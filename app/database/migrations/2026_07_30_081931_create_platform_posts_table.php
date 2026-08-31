<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Posts published to Google Business Profile and social platforms
 * (DATA-MODEL §5.11). AI writes posts — never reviews.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_posts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // google|facebook|instagram — no CREATE TYPE in the spec, so a
            // plain string, no enum cast.
            $table->string('platform');

            $table->string('external_id')->nullable();

            $table->text('content');
            $table->string('media_url')->nullable();

            // Set when the post recycles a review (with permission rules
            // applied upstream); the post outlives the review row.
            $table->foreignId('source_review_id')->nullable()
                ->constrained('reviews')->nullOnDelete();

            $table->string('status')->default('draft');

            $table->timestamp('hold_until')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['business_id', 'status']);
        });

        DB::statement('ALTER TABLE platform_posts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE platform_posts FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON platform_posts
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_posts');
    }
};
