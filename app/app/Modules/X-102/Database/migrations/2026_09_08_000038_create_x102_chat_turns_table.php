<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_turns')) {
            Schema::create('chat_turns', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('chat_session_id')->constrained('chat_sessions')->cascadeOnDelete();
                $table->string('author_type')->default('visitor');
                $table->text('message')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasColumn('chat_turns', 'business_id')) {
            DB::statement('ALTER TABLE chat_turns ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE chat_turns FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON chat_turns');

            DB::statement(<<<'SQL'
                CREATE POLICY tenant_isolation ON chat_turns
                    USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                    WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_turns');
    }
};
