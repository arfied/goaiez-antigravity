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
        if (! Schema::hasTable('outreach_ladders')) {
            Schema::create('outreach_ladders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('person_id')->index();
                $table->string('status')->default('active'); // active, paused, halted, replied
                $table->boolean('distress_signal_detected')->default(false);
                $table->boolean('research_triggered')->default(false); // TEST ANCHOR: healthy prospect never triggers research
                $table->boolean('exclusive_sms_mode')->default(false); // TEST ANCHOR: reply shifts to SMS
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ladder_steps')) {
            Schema::create('ladder_steps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('ladder_id')->constrained('outreach_ladders')->cascadeOnDelete();
                $table->unsignedSmallInteger('rung_number');
                $table->string('channel'); // email, voice_drop, sms, direct_mail
                $table->string('status')->default('pending'); // pending, sent, cancelled
                $table->text('content_preview')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['outreach_ladders', 'ladder_steps'];

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
        Schema::dropIfExists('ladder_steps');
        Schema::dropIfExists('outreach_ladders');
    }
};
