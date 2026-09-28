<?php

declare(strict_types=1);

use App\Enums\FetchMethodCeiling;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notes about a nearby peer's website — title, description, headings — read
 * through the fetch gateway under its own source key. INPUT for the AI to write
 * a business's own copy fresh. ⛔ Never the page text, never an image: the owner's
 * one rule is that nothing of theirs is copied, and the schema has no column to
 * hold it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_site_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('title', 300)->nullable();
            $table->string('description', 600)->nullable();
            $table->jsonb('headings')->nullable();
            // noted | refused | failed
            $table->string('status', 16);
            $table->string('refusal_reason', 64)->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['competitor_id']);
            $table->index(['business_id', 'fetched_at']);
        });

        DB::statement('ALTER TABLE competitor_site_notes ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE competitor_site_notes FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON competitor_site_notes
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);

        DB::table('fetch_sources')->insertOrIgnore([
            'key' => 'competitor_site',
            'class' => null,
            'method_ceiling' => FetchMethodCeiling::LightFetch->value,
            'robots_respect' => true,
            'rate_budget' => json_encode(['per_minute' => 2, 'per_day' => 200]),
            'counsel_note_ref' => null,
            'kill' => false,
            'updated_by' => 'seed:local-search-wave-2',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('fetch_sources')->where('key', 'competitor_site')->delete();
        Schema::dropIfExists('competitor_site_notes');
    }
};
