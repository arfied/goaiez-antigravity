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
        if (! Schema::hasTable('promotions')) {
            Schema::create('promotions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('code')->index();
                $table->string('discount_type')->default('percentage'); // percentage, fixed_cents
                $table->unsignedInteger('discount_value');
                $table->unsignedInteger('max_redemptions')->default(100);
                $table->unsignedInteger('redemptions_count')->default(0);
                $table->unsignedInteger('velocity_threshold_per_hour')->default(10);
                $table->boolean('is_active')->default(true);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('promotion_scopes')) {
            Schema::create('promotion_scopes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
                $table->string('scope_type'); // service_category, customer_segment, territory
                $table->string('scope_value');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('promotion_redemptions')) {
            Schema::create('promotion_redemptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
                $table->unsignedBigInteger('customer_id');
                $table->string('order_id')->index();
                $table->bigInteger('discount_applied_cents');
                $table->timestamp('redeemed_at');
                $table->timestamps();
            });
        }

        $tables = ['promotions', 'promotion_scopes', 'promotion_redemptions'];

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
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('promotion_scopes');
        Schema::dropIfExists('promotions');
    }
};
