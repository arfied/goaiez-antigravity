<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The owner-visible history of everything autopilot did (DATA-MODEL §5.12).
 *
 * APPEND-ONLY (§5.14): updates and deletes are blocked at the model layer —
 * see ActivityFeedItem. Rows have created_at only; an updated_at on an
 * append-only table would be a column that must never differ from created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_feed', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Nullable: most actions belong to a location, but system messages
            // and business-level events (billing, brand registration) do not.
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('action_type');

            // Plain-language, jargon-free, outcome language (`22`). The title
            // is what the feed *is*, so it is required.
            $table->string('title');

            $table->jsonb('metadata')->nullable();

            $table->timestamp('created_at')->nullable();
        });

        // DATA-MODEL §5.13: idx_activity_business_created. Raw because the
        // schema builder cannot express per-column direction.
        DB::statement(
            'CREATE INDEX idx_activity_business_created
                 ON activity_feed (business_id, created_at DESC)'
        );

        DB::statement('ALTER TABLE activity_feed ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE activity_feed FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON activity_feed
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_feed');
    }
};
