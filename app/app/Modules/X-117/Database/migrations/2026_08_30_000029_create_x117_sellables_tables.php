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
        if (! Schema::hasTable('sellables')) {
            Schema::create('sellables', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->string('sku')->index();
                $table->unsignedInteger('inventory_quantity')->default(0);
                $table->string('fulfilment_type')->default('service'); // physical, digital, service, rental, subscription, event
                $table->foreignId('price_item_id')->nullable()->constrained('price_book_items')->nullOnDelete();
                $table->bigInteger('unit_price_cents')->default(0); // minor units, integers, no floats (§143-144)
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('carts')) {
            Schema::create('carts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('session_token')->index();
                $table->jsonb('items');
                $table->bigInteger('total_cents')->default(0);
                $table->timestamp('expires_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('people')->nullOnDelete();
                $table->string('order_number')->index();
                $table->string('status')->default('paid'); // paid, cancelled, sold_out
                $table->bigInteger('total_cents')->default(0);
                $table->string('auth_token')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_lines')) {
            Schema::create('order_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('sellable_id')->constrained('sellables')->cascadeOnDelete();
                $table->unsignedInteger('quantity')->default(1);
                $table->bigInteger('unit_price_cents')->default(0);
                $table->bigInteger('subtotal_cents')->default(0);
                $table->timestamps();
            });
        }

        $tables = ['sellables', 'carts', 'orders', 'order_lines'];

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
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('sellables');
    }
};
