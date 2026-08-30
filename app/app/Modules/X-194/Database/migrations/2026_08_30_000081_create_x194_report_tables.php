<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('saved_views')) {
            Schema::create('saved_views', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('view_name');
                $table->string('view_type')->default('table');
                $table->jsonb('filter_config')->nullable();
                $table->jsonb('columns_config')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('view_schedules')) {
            Schema::create('view_schedules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('saved_view_id')->constrained('saved_views')->cascadeOnDelete();
                $table->string('cron_expression')->default('0 8 * * *');
                $table->jsonb('recipient_emails');
                $table->string('timezone')->default('UTC');
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['saved_views', 'view_schedules'];

        foreach ($tables as $table) {
            app('db')->statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            app('db')->statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            app('db')->statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");

            app('db')->statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$table}
                    USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                    WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('view_schedules');
        Schema::dropIfExists('saved_views');
    }
};
