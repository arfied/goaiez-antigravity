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
        if (! Schema::hasTable('agent_turns')) {
            Schema::create('agent_turns', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('conversation_id')->nullable();
                $table->unsignedInteger('turn_number')->default(1);
                $table->text('user_message');
                $table->text('agent_reply');
                $table->string('status')->default('answered'); // answered, refused, handoff
                $table->string('refusal_code')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('agent_refusals')) {
            Schema::create('agent_refusals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('turn_id')->nullable()->constrained('agent_turns')->nullOnDelete();
                $table->string('refusal_code');
                $table->string('reason');
                $table->text('user_input')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('agent_instructions')) {
            Schema::create('agent_instructions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('instruction_key')->index();
                $table->text('instruction_text');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $tables = ['agent_turns', 'agent_refusals', 'agent_instructions'];

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
        Schema::dropIfExists('agent_instructions');
        Schema::dropIfExists('agent_refusals');
        Schema::dropIfExists('agent_turns');
    }
};
