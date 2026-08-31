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
        if (! Schema::hasTable('event_log')) {
            Schema::create('event_log', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('event_name');
                $table->jsonb('payload');
                $table->string('partition_key')->nullable()->index();
                $table->bigInteger('sequence_number')->default(0);
                $table->string('status')->default('published');
                $table->timestamp('published_at')->useCurrent();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('event_subscriptions')) {
            Schema::create('event_subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('event_name');
                $table->string('target_url');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('failure_count')->default(0);
                $table->timestamp('last_failed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dead_letters')) {
            Schema::create('dead_letters', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('event_log_id')->constrained('event_log')->cascadeOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained('event_subscriptions')->nullOnDelete();
                $table->text('error_message');
                $table->unsignedInteger('attempts')->default(10);
                $table->timestamp('notified_at')->nullable();
                $table->timestamp('replayed_at')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['event_log', 'event_subscriptions', 'dead_letters'];

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
        Schema::dropIfExists('dead_letters');
        Schema::dropIfExists('event_subscriptions');
        Schema::dropIfExists('event_log');
    }
};
