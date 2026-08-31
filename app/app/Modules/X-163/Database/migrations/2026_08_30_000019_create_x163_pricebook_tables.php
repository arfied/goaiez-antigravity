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
        if (! Schema::hasTable('location_books')) {
            Schema::create('location_books', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('location_name')->index();
                $table->string('area_code', 10)->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('price_book_items')) {
            Schema::create('price_book_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('location_book_id')->nullable()->constrained('location_books')->nullOnDelete();
                $table->string('service_name')->index();
                $table->bigInteger('price_cents')->default(0);
                $table->bigInteger('price_min_cents')->nullable();
                $table->bigInteger('price_max_cents')->nullable();
                $table->boolean('is_sample')->default(false);
                $table->boolean('is_confirmed')->default(true);
                $table->float('tax_rate_pct')->default(0.0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('price_book_versions')) {
            Schema::create('price_book_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('location_book_id')->nullable()->constrained('location_books')->nullOnDelete();
                $table->unsignedInteger('version')->default(1);
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('callout_fees')) {
            Schema::create('callout_fees', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('location_book_id')->nullable()->constrained('location_books')->nullOnDelete();
                $table->bigInteger('callout_fee_cents')->default(7500); // $75.00
                $table->boolean('deducted_if_proceeding')->default(true);
                $table->text('explanation_text')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['location_books', 'price_book_items', 'price_book_versions', 'callout_fees'];

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
        Schema::dropIfExists('callout_fees');
        Schema::dropIfExists('price_book_versions');
        Schema::dropIfExists('price_book_items');
        Schema::dropIfExists('location_books');
    }
};
