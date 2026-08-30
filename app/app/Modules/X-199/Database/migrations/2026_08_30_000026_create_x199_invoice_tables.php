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
        if (! Schema::hasTable('credit_terms')) {
            Schema::create('credit_terms', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('people')->nullOnDelete();
                $table->string('terms_type')->default('net_30'); // net_30, net_15, due_on_receipt
                $table->bigInteger('credit_limit_cents')->default(500000); // $5,000.00
                $table->bigInteger('current_outstanding_cents')->default(0);
                $table->string('card_on_file_token')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('people')->nullOnDelete();
                $table->string('invoice_number')->index();
                $table->bigInteger('total_cents')->default(0);
                $table->bigInteger('paid_cents')->default(0);
                $table->string('status')->default('draft'); // draft, issued, paid, due, offline_recorded
                $table->date('due_date');
                $table->string('pdf_url')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('invoice_lines')) {
            Schema::create('invoice_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->string('description');
                $table->unsignedInteger('quantity')->default(1);
                $table->bigInteger('unit_price_cents')->default(0);
                $table->bigInteger('subtotal_cents')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('overflow_charges')) {
            Schema::create('overflow_charges', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('people')->nullOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->string('charge_type'); // overflow_charged, overflow_reversed
                $table->bigInteger('amount_cents');
                $table->string('card_token');
                $table->string('reference_id')->index();
                $table->timestamps();
            });
        }

        $tables = ['credit_terms', 'invoices', 'invoice_lines', 'overflow_charges'];

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
        Schema::dropIfExists('overflow_charges');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('credit_terms');
    }
};
