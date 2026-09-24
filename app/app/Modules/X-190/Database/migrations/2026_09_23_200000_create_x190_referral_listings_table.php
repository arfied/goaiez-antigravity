<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('referral_listings')) {
            Schema::create('referral_listings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
                $table->string('company_name');
                $table->string('category');
                $table->string('territory_zip')->index();
                $table->boolean('is_listed')->default(true);
                $table->timestamps();
            });

            DB::statement('ALTER TABLE referral_listings ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE referral_listings FORCE ROW LEVEL SECURITY');
            DB::statement('DROP POLICY IF EXISTS tenant_isolation ON referral_listings');
            DB::statement("
                CREATE POLICY tenant_isolation ON referral_listings
                    USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                    WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
            ");

            DB::statement('DROP POLICY IF EXISTS directory_lookup ON referral_listings');
            DB::statement('
                CREATE POLICY directory_lookup ON referral_listings
                    FOR SELECT
                    USING (is_listed = true)
            ');
        }
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON referral_listings');
        DB::statement('DROP POLICY IF EXISTS directory_lookup ON referral_listings');
        Schema::dropIfExists('referral_listings');
    }
};
