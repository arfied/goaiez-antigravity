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
        if (! Schema::hasTable('referral_slots')) {
            Schema::create('referral_slots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('category');
                $table->string('territory_zip')->index();
                $table->boolean('is_network_enabled')->default(true); // TEST ANCHOR: network ON with zero taps
                $table->string('status')->default('open'); // open, proposed, filled, declined
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('partner_pool')) {
            Schema::create('partner_pool', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('company_name');
                $table->string('category');
                $table->string('territory_zip')->index();
                $table->jsonb('research_data')->nullable(); // TEST ANCHOR: declined research row reused
                $table->unsignedInteger('fetch_count')->default(1);
                $table->boolean('is_declined')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('referrals')) {
            Schema::create('referrals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('slot_id')->nullable()->constrained('referral_slots')->nullOnDelete();
                $table->string('customer_name');
                $table->string('referral_code')->unique();
                $table->string('discount_offer');
                $table->boolean('package_delivered')->default(true); // TEST ANCHOR: ships whether or not partner buys
                $table->boolean('partner_bought')->default(false);
                $table->timestamps();
            });
        }

        $tables = ['referral_slots', 'partner_pool', 'referrals'];

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
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('partner_pool');
        Schema::dropIfExists('referral_slots');
    }
};
