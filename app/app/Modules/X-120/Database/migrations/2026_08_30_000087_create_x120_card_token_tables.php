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
        if (! Schema::hasTable('card_tokens')) {
            Schema::create('card_tokens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('gateway_customer_id');
                $table->string('gateway_payment_method_id')->index();
                $table->string('brand')->default('visa');
                $table->string('last_four', 4);
                $table->unsignedSmallInteger('exp_month');
                $table->unsignedSmallInteger('exp_year');
                $table->boolean('is_default')->default(true);
                $table->boolean('alert_sent')->default(false); // Exactly one alert, not a sequence (TEST ANCHOR)
                $table->timestamps();
            });
        }

        $tables = ['card_tokens'];

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
        Schema::dropIfExists('card_tokens');
    }
};
