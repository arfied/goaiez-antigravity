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
        if (! Schema::hasTable('whatsapp_templates')) {
            Schema::create('whatsapp_templates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->string('category')->default('utility'); // utility, marketing, authentication
                $table->string('language')->default('en_US');
                $table->text('body_text');
                $table->string('status')->default('pending_approval'); // pending_approval, approved, rejected
                $table->string('meta_template_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('whatsapp_sessions')) {
            Schema::create('whatsapp_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('recipient_phone')->index();
                $table->timestamp('last_inbound_at')->nullable();
                $table->timestamp('session_window_expires_at')->nullable(); // 24h window (TEST ANCHOR)
                $table->boolean('is_window_open')->default(false);
                $table->timestamps();
            });
        }

        $tables = ['whatsapp_templates', 'whatsapp_sessions'];

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
        Schema::dropIfExists('whatsapp_sessions');
        Schema::dropIfExists('whatsapp_templates');
    }
};
