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
        if (! Schema::hasTable('call_sessions')) {
            Schema::create('call_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('call_sid')->index();
                $table->string('from_phone');
                $table->string('to_phone');
                $table->string('status')->default('ringing'); // ringing, answered, missed, completed
                $table->unsignedInteger('latency_ms')->default(350);
                $table->boolean('fallback_triggered')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('call_turns')) {
            Schema::create('call_turns', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('session_id')->constrained('call_sessions')->cascadeOnDelete();
                $table->unsignedInteger('turn_index')->default(1);
                $table->string('speaker')->default('agent');
                $table->text('transcript');
                $table->unsignedInteger('barge_in_latency_ms')->default(120);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('voicemails')) {
            Schema::create('voicemails', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('call_session_id')->constrained('call_sessions')->cascadeOnDelete();
                $table->string('audio_url')->nullable();
                $table->text('transcription')->nullable();
                $table->unsignedInteger('duration_seconds')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('call_autopsies')) {
            Schema::create('call_autopsies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('call_session_id')->constrained('call_sessions')->cascadeOnDelete();
                $table->jsonb('metrics')->nullable();
                $table->string('sentiment')->default('neutral');
                $table->text('coaching_notes')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['call_sessions', 'call_turns', 'voicemails', 'call_autopsies'];

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
        Schema::dropIfExists('call_autopsies');
        Schema::dropIfExists('voicemails');
        Schema::dropIfExists('call_turns');
        Schema::dropIfExists('call_sessions');
    }
};
