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
        if (! Schema::hasTable('operator_alerts')) {
            Schema::create('operator_alerts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('severity')->default('warning'); // info, warning, critical
                $table->text('action_verb_message'); // Every alert row's message begins with a verb (TEST ANCHOR, G21-05)
                $table->string('status')->default('open');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ip_bans')) {
            Schema::create('ip_bans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('ip_address')->index();
                $table->string('reason');
                $table->timestamp('expires_at')->nullable(); // TTL support (G17-07)
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tenant_tickets')) {
            Schema::create('tenant_tickets', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('source')->default('human_requested');
                $table->string('category')->default('general');
                $table->text('full_transcript'); // Full transcript preserved within 1 minute (TEST ANCHOR)
                $table->timestamp('sla_due_at');
                $table->string('status')->default('open');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('manual_queue')) {
            Schema::create('manual_queue', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('task_type');
                $table->jsonb('payload');
                $table->string('status')->default('queued');
                $table->timestamps();
            });
        }

        $tables = ['operator_alerts', 'ip_bans', 'tenant_tickets', 'manual_queue'];

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
        Schema::dropIfExists('manual_queue');
        Schema::dropIfExists('tenant_tickets');
        Schema::dropIfExists('ip_bans');
        Schema::dropIfExists('operator_alerts');
    }
};
