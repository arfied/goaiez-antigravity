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
        if (! Schema::hasTable('commission_rules')) {
            Schema::create('commission_rules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->string('rule_type')->default('gross_profit_pct'); // gross_profit_pct, revenue_tier, bonus
                $table->decimal('percentage', 5, 2)->default(10.00);
                $table->unsignedInteger('threshold_cents')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('commissions')) {
            Schema::create('commissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->unsignedBigInteger('staff_id')->index();
                $table->unsignedInteger('amount_cents');
                $table->string('status')->default('pending_cash_collection'); // pending_cash_collection, released, clawed_back (TEST ANCHOR, G9-29)
                $table->string('payment_id')->nullable();
                $table->unsignedInteger('clawback_amount_cents')->default(0);
                $table->string('clawback_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('scorecards')) {
            Schema::create('scorecards', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('staff_id')->index();
                $table->string('period_key')->index(); // e.g. 2026-Q3
                $table->unsignedInteger('revenue_collected_cents')->default(0);
                $table->unsignedInteger('commissions_earned_cents')->default(0);
                $table->decimal('average_review_score', 3, 2)->default(5.00);
                $table->timestamps();
            });
        }

        $tables = ['commission_rules', 'commissions', 'scorecards'];

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
        Schema::dropIfExists('scorecards');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('commission_rules');
    }
};
