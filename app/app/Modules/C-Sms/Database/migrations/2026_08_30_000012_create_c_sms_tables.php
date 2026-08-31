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
        if (! Schema::hasTable('sms_compositions')) {
            Schema::create('sms_compositions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('recipient_phone')->index();
                $table->string('message_class')->default('transactional'); // transactional, marketing
                $table->text('body');
                $table->unsignedInteger('segments_count')->default(1);
                $table->string('encoding')->default('gsm7'); // gsm7, ucs2
                $table->string('status')->default('sent'); // draft, scheduled, sent, halted
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sms_moderation_results')) {
            Schema::create('sms_moderation_results', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('composition_id')->constrained('sms_compositions')->cascadeOnDelete();
                $table->boolean('passed')->default(true);
                $table->jsonb('warnings')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['sms_compositions', 'sms_moderation_results'];

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
        Schema::dropIfExists('sms_moderation_results');
        Schema::dropIfExists('sms_compositions');
    }
};
