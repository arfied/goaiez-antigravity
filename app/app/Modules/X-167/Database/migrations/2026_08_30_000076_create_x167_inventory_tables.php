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
        if (! Schema::hasTable('stock_locations')) {
            Schema::create('stock_locations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->string('type')->default('van'); // van, storage_unit, warehouse
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->string('email')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('stock_items')) {
            Schema::create('stock_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
                $table->string('sku')->index();
                $table->string('barcode')->nullable()->index();
                $table->string('name');
                $table->decimal('quantity', 12, 4)->default(0); // Fractional units (G6-14, TEST ANCHOR)
                $table->string('unit')->default('units'); // meters, units, spools, kg
                $table->decimal('reorder_point', 12, 4)->default(5);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->string('po_number')->index();
                $table->jsonb('items');
                $table->bigInteger('total_cents')->default(0);
                $table->string('status')->default('proposed'); // proposed, approved, sent
                $table->string('approved_action_id')->nullable(); // No PO is marked sent without an approval action row (TEST ANCHOR)
                $table->timestamps();
            });
        }

        $tables = ['stock_locations', 'suppliers', 'stock_items', 'purchase_orders'];

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
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('stock_locations');
    }
};
