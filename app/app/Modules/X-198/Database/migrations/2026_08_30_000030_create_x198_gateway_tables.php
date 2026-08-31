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
        if (! Schema::hasTable('merchant_connections')) {
            Schema::create('merchant_connections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('gateway_name'); // stripe, square, clover, plaid
                $table->string('merchant_account_id')->index();
                $table->boolean('is_connected')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('merchant_connection_id')->nullable()->constrained('merchant_connections')->nullOnDelete();
                $table->string('gateway_charge_id')->index();
                $table->bigInteger('amount_cents');
                $table->string('currency')->default('USD');
                $table->string('payment_token');
                $table->string('idempotency_key')->index();
                $table->string('status')->default('captured'); // captured, failed, refunded, chargeback
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payouts')) {
            Schema::create('payouts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('merchant_connection_id')->nullable()->constrained('merchant_connections')->nullOnDelete();
                $table->string('gateway_payout_id')->index();
                $table->bigInteger('amount_cents');
                $table->string('status')->default('reconciled'); // reconciled, pending, discrepancy
                $table->date('payout_date');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('reconciliation_runs')) {
            Schema::create('reconciliation_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('payout_id')->nullable()->constrained('payouts')->nullOnDelete();
                $table->bigInteger('expected_cents');
                $table->bigInteger('actual_cents');
                $table->bigInteger('discrepancy_cents')->default(0);
                $table->text('discrepancy_reason')->nullable();
                $table->string('status')->default('balanced'); // balanced, discrepancy_logged
                $table->timestamps();
            });
        }

        $tables = ['merchant_connections', 'payments', 'payouts', 'reconciliation_runs'];

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
        Schema::dropIfExists('reconciliation_runs');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('merchant_connections');
    }
};
