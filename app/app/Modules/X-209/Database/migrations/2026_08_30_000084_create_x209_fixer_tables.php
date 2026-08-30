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
        if (! Schema::hasTable('fixer_commands')) {
            Schema::create('fixer_commands', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('staff_person_id')->index();
                $table->text('raw_command');
                $table->string('parsed_intent');
                $table->unsignedBigInteger('job_id')->nullable()->index();
                $table->unsignedInteger('eta_minutes_delayed')->default(0);
                $table->string('consent_decision')->default('approved');
                $table->string('outbound_message_id')->nullable();
                $table->string('status')->default('executed'); // executed, escalated, pending_approval
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('fixer_ladder')) {
            Schema::create('fixer_ladder', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('action_name');
                $table->unsignedInteger('current_level')->default(3);
                $table->unsignedInteger('success_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('staff_calendar_links')) {
            Schema::create('staff_calendar_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('staff_person_id')->index();
                $table->string('calendar_provider');
                $table->string('sync_token')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $tables = ['fixer_commands', 'fixer_ladder', 'staff_calendar_links'];

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
        Schema::dropIfExists('staff_calendar_links');
        Schema::dropIfExists('fixer_ladder');
        Schema::dropIfExists('fixer_commands');
    }
};
