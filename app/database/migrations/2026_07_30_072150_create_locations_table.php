<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Locations — the first ordinary tenant-owned table, and the pattern every
 * later one repeats.
 *
 * Authority: docs/DATA-MODEL.md §5.2 plus its reconciliation notes, which add
 * the place-id resolution fields (decisions 105-108).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('google_location_id')->nullable();

            // google_cid is the permanent identifier. Place IDs change on
            // listing merges, so google_place_id is re-validated periodically
            // and alerted on rather than trusted (DATA-MODEL §5.2).
            $table->string('google_place_id')->nullable();
            $table->string('google_cid')->nullable();

            // The owner-pasted Google URL, kept verbatim. This is the
            // re-resolution input when a place id goes stale — without it,
            // recovering from a listing merge means asking the owner to paste
            // the link again (decisions 105-108).
            $table->text('google_maps_url')->nullable();

            $table->string('name');
            $table->string('address')->nullable();
            $table->string('primary_phone')->nullable();
            $table->string('website_url')->nullable();
            $table->string('timezone')->nullable();

            // Google's own rating, one decimal. Never a filtered or 5-star-only
            // aggregate (`29` §2 rule 5).
            $table->decimal('current_rating', 2, 1)->nullable();
            $table->integer('review_count')->default(0);

            $table->boolean('is_autopilot_active')->default(true);
            $table->integer('boost_score')->nullable();

            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Composite with business_id, never global. A unique index on
            // google_location_id alone would let one business's imported
            // location collide with another's — and the collision would reveal
            // that the other business exists.
            $table->unique(['business_id', 'google_location_id']);

            // business_id first: PostgreSQL btree indexes are usable on a
            // leftmost prefix, so this also serves "all locations for a
            // business", which is the commonest query on this table.
            $table->index(['business_id', 'is_autopilot_active']);
        });

        DB::statement('ALTER TABLE locations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE locations FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON locations
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
