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
        if (! Schema::hasTable('chat_sessions')) {
            Schema::create('chat_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('session_token')->unique();
                $table->string('visitor_ip')->nullable();
                $table->string('status')->default('active'); // active, lead_captured, offline_form, escalated
                $table->unsignedInteger('rage_clicks_count')->default(0); // TEST ANCHOR: 4 rage clicks escalate
                $table->boolean('is_ai_capped')->default(false); // TEST ANCHOR: renders offline form if true
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_leads')) {
            Schema::create('chat_leads', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('chat_session_id')->constrained('chat_sessions')->cascadeOnDelete();
                $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete(); // TEST ANCHOR: creates Person
                $table->string('name');
                $table->string('phone');
                $table->string('email')->nullable();
                $table->text('message')->nullable();
                $table->string('form_type')->default('live_chat'); // live_chat, offline_capped_form
                $table->timestamps();
            });
        }

        $tables = ['chat_sessions', 'chat_leads'];

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
        Schema::dropIfExists('chat_leads');
        Schema::dropIfExists('chat_sessions');
    }
};
