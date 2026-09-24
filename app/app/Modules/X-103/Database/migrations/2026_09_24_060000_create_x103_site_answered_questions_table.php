<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which customer questions have been answered on a page.
     * The source_id refers to the chat turn or form submission ID, but it is not
     * a foreign key because this record must outlive the deletion of the original turn.
     * The question is also denormalised here so it survives.
     */
    public function up(): void
    {
        if (! Schema::hasTable('site_answered_questions')) {
            Schema::create('site_answered_questions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('source_type'); // 'chat'|'form'
                $table->unsignedBigInteger('source_id');
                $table->text('question');
                $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
                $table->timestamp('answered_at')->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'source_type', 'source_id']);
            });

            $table = 'site_answered_questions';
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
        Schema::dropIfExists('site_answered_questions');
    }
};
