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
        if (! Schema::hasTable('estimates')) {
            Schema::create('estimates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('people')->nullOnDelete();
                $table->string('estimate_number')->index();
                $table->unsignedInteger('price_book_version')->default(1);
                $table->string('status')->default('draft'); // draft, sent, accepted, expired
                $table->bigInteger('total_cents')->default(0);
                $table->bigInteger('deposit_amount_cents')->default(0);
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->string('signed_by_customer')->nullable();
                $table->string('countersigned_by_staff')->nullable();
                $table->string('executed_doc_url')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('estimate_lines')) {
            Schema::create('estimate_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('estimate_id')->constrained('estimates')->cascadeOnDelete();
                $table->string('service_name');
                $table->unsignedInteger('quantity')->default(1);
                $table->bigInteger('unit_price_cents')->default(0);
                $table->bigInteger('subtotal_cents')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('estimate_versions')) {
            Schema::create('estimate_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('estimate_id')->constrained('estimates')->cascadeOnDelete();
                $table->unsignedInteger('version_number')->default(1);
                $table->jsonb('frozen_snapshot');
                $table->timestamps();
            });
        }

        $tables = ['estimates', 'estimate_lines', 'estimate_versions'];

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
        Schema::dropIfExists('estimate_versions');
        Schema::dropIfExists('estimate_lines');
        Schema::dropIfExists('estimates');
    }
};
