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
        if (! Schema::hasTable('call_campaigns')) {
            Schema::create('call_campaigns', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->decimal('abandonment_ceiling_pct', 4, 2)->default(3.00); // G3-04, G10-03: hard max 3.00%
                $table->boolean('is_running')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dialer_seats')) {
            Schema::create('dialer_seats', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('agent_name');
                $table->boolean('is_ai_agent')->default(false); // G2-09: AI seat scored like human
                $table->boolean('is_logged_in')->default(false);
                $table->string('state')->default('idle'); // idle, dialing, connected, wrap_up
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('call_dispositions')) {
            Schema::create('call_dispositions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('campaign_id')->constrained('call_campaigns')->cascadeOnDelete();
                $table->foreignId('seat_id')->constrained('dialer_seats')->cascadeOnDelete();
                $table->string('phone');
                $table->string('disposition'); // answered, busy, voicemail, no_answer, sale_won
                $table->boolean('is_uncertain_human')->default(false); // G18-01: uncertain treat as human
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('qa_scorecards')) {
            Schema::create('qa_scorecards', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('seat_id')->constrained('dialer_seats')->cascadeOnDelete();
                $table->unsignedBigInteger('call_id');
                $table->unsignedSmallInteger('score')->default(100);
                $table->string('coaching_note')->nullable();
                $table->boolean('is_positive_only')->default(true); // G2-35, G16-15: positive scorecard
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('call_schedules')) {
            Schema::create('call_schedules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('phone');
                $table->timestamp('scheduled_at');
                $table->boolean('is_completed')->default(false);
                $table->timestamps();
            });
        }

        $tables = ['call_campaigns', 'dialer_seats', 'call_dispositions', 'qa_scorecards', 'call_schedules'];

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
        Schema::dropIfExists('call_schedules');
        Schema::dropIfExists('qa_scorecards');
        Schema::dropIfExists('call_dispositions');
        Schema::dropIfExists('dialer_seats');
        Schema::dropIfExists('call_campaigns');
    }
};
