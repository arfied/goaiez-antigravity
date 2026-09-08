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
        Schema::create('c_agent_takeovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('conversation_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'conversation_id']);
        });

        DB::statement('ALTER TABLE c_agent_takeovers ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE c_agent_takeovers FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON c_agent_takeovers
                USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('c_agent_takeovers');
    }
};
