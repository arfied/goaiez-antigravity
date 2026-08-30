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
        if (! Schema::hasTable('videos')) {
            Schema::create('videos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('title');
                $table->unsignedInteger('demo_number')->index(); // TEST ANCHOR: transcript contains demo number as digits
                $table->string('video_url');
                $table->string('caption_track_url'); // TEST ANCHOR: every rendered video has a caption track
                $table->text('transcript'); // TEST ANCHOR
                $table->boolean('is_rendered')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('video_views')) {
            Schema::create('video_views', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
                $table->string('viewer_session_id')->index();
                $table->unsignedInteger('watch_duration_seconds')->default(0);
                $table->decimal('watch_depth_percent', 5, 2)->default(0.00); // TEST ANCHOR
                $table->boolean('passed_50_percent')->default(false); // TEST ANCHOR: past 50% writes watched
                $table->timestamps();
            });
        }

        $tables = ['videos', 'video_views'];

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
        Schema::dropIfExists('video_views');
        Schema::dropIfExists('videos');
    }
};
