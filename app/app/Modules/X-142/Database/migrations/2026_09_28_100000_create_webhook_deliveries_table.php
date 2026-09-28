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
        if (! Schema::hasTable('webhook_deliveries')) {
            Schema::create('webhook_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('subscription_id')->constrained('webhook_subscriptions')->cascadeOnDelete();
                $table->string('event');
                $table->string('delivery_ref')->unique();
                $table->jsonb('payload');
                $table->string('status');
                $table->unsignedInteger('attempts')->default(0);
                $table->integer('last_status_code')->nullable();
                $table->string('last_error', 255)->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();
            });
        }

        $table = 'webhook_deliveries';
        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");

        DB::statement(<<<SQL
            CREATE POLICY tenant_isolation ON {$table}
                USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
