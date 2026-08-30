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
        if (! Schema::hasTable('receivable_states')) {
            Schema::create('receivable_states', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->unsignedInteger('age_days')->default(0);
                $table->bigInteger('late_fee_cents')->default(0);
                $table->string('status')->default('current'); // current, overdue, payment_plan, escalated, packaged_collections
                $table->foreignId('escalated_to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_plans')) {
            Schema::create('payment_plans', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->unsignedSmallInteger('installments_count')->default(3);
                $table->bigInteger('installment_amount_cents');
                $table->string('frequency')->default('monthly'); // weekly, biweekly, monthly
                $table->string('status')->default('offered'); // offered, accepted, completed, defaulted
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('offline_payments')) {
            Schema::create('offline_payments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->bigInteger('amount_cents');
                $table->string('payment_method')->default('check'); // cash, check, zelle, wire
                $table->string('reference_number')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['receivable_states', 'payment_plans', 'offline_payments'];

        foreach ($tables as $table) {
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

    public function down(): void
    {
        Schema::dropIfExists('offline_payments');
        Schema::dropIfExists('payment_plans');
        Schema::dropIfExists('receivable_states');
    }
};
