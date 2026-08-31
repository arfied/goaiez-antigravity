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
        if (! Schema::hasTable('affiliates')) {
            Schema::create('affiliates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('affiliate_code')->unique();
                $table->string('partner_name');
                $table->unsignedInteger('commission_rate_bps')->default(1000); // 10%
                $table->bigInteger('lifetime_earnings_cents')->default(0); // G13-20: lifetime balance
                $table->bigInteger('current_balance_cents')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('affiliate_attributions')) {
            Schema::create('affiliate_attributions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
                $table->string('order_id')->index();
                $table->bigInteger('sale_amount_cents');
                $table->bigInteger('commission_cents');
                $table->timestamp('attributed_at');
                $table->boolean('is_clawed_back')->default(false);
                $table->string('clawback_status')->default('none'); // none, proposed, approved
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('affiliate_payouts')) {
            Schema::create('affiliate_payouts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
                $table->bigInteger('amount_cents');
                $table->string('status')->default('requested'); // requested, approved, paid
                $table->boolean('money_moved')->default(false); // TEST ANCHOR: proposals move NO money
                $table->timestamps();
            });
        }

        $tables = ['affiliates', 'affiliate_attributions', 'affiliate_payouts'];

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
        Schema::dropIfExists('affiliate_payouts');
        Schema::dropIfExists('affiliate_attributions');
        Schema::dropIfExists('affiliates');
    }
};
