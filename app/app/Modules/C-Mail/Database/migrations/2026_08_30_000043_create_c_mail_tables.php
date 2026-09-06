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
        if (! Schema::hasTable('mail_domains')) {
            Schema::create('mail_domains', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('domain_name')->index();
                $table->string('dkim_status')->default('verified');
                $table->string('spf_status')->default('verified');
                $table->string('dmarc_status')->default('quarantine'); // G10-28
                $table->boolean('is_marketing_paused')->default(false); // TEST ANCHOR: complaint crossing 0.10%
                $table->decimal('complaint_rate', 6, 4)->default(0.0000);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('warmup_calendars')) {
            Schema::create('warmup_calendars', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('mail_domain_id')->constrained('mail_domains')->cascadeOnDelete();
                $table->unsignedInteger('current_day')->default(1);
                $table->unsignedInteger('daily_allowance')->default(50); // TEST ANCHOR
                $table->unsignedInteger('sent_today')->default(0);
                $table->boolean('is_warmed')->default(false);
                $table->jsonb('schedule')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mail_events')) {
            Schema::create('mail_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('mail_domain_id')->constrained('mail_domains')->cascadeOnDelete();
                $table->string('event_type'); // sent, queued, unsubscribed, bounced, complained, replied, spam-trap
                $table->string('send_type')->default('marketing'); // marketing, conversational, transactional
                $table->string('recipient_email');
                $table->string('subject');
                $table->jsonb('payload')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['mail_domains', 'warmup_calendars', 'mail_events'];

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
        Schema::dropIfExists('mail_events');
        Schema::dropIfExists('warmup_calendars');
        Schema::dropIfExists('mail_domains');
    }
};
