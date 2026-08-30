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
        if (! Schema::hasTable('content_topics')) {
            Schema::create('content_topics', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('topic_title')->index();
                $table->string('slug')->index();
                $table->string('cluster_key')->default('general_faq');
                $table->decimal('similarity_score', 4, 3)->default(0.000); // G8-24
                $table->boolean('is_published')->default(false); // TEST ANCHOR: sample price fails gate
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('topic_sources')) {
            Schema::create('topic_sources', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('topic_id')->constrained('content_topics')->cascadeOnDelete();
                $table->string('source_type'); // conversation_turn, refusal, customer_inquiry
                $table->string('refusal_id')->nullable()->index(); // TEST ANCHOR: page from refusal cites refusal id
                $table->string('conversation_ref')->nullable();
                $table->text('raw_content');
                $table->timestamps();
            });
        }

        $tables = ['content_topics', 'topic_sources'];

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
        Schema::dropIfExists('topic_sources');
        Schema::dropIfExists('content_topics');
    }
};
