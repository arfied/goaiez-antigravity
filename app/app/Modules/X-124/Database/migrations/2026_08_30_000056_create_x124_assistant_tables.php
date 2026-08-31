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
        if (! Schema::hasTable('assistant_sessions')) {
            Schema::create('assistant_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('session_token')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->jsonb('context')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assistant_recommendations')) {
            Schema::create('assistant_recommendations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('session_id')->constrained('assistant_sessions')->cascadeOnDelete();
                $table->string('title');
                $table->string('action_key');
                $table->string('status')->default('active'); // active, executed, dismissed
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assistant_unsupported')) {
            Schema::create('assistant_unsupported', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('session_id')->constrained('assistant_sessions')->cascadeOnDelete();
                $table->text('utterance');
                $table->string('response_returned')->default("I can't do that yet"); // TEST ANCHOR
                $table->timestamps();
            });
        }

        $tables = ['assistant_sessions', 'assistant_recommendations', 'assistant_unsupported'];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'business_id')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_unsupported');
        Schema::dropIfExists('assistant_recommendations');
        Schema::dropIfExists('assistant_sessions');
    }
};
