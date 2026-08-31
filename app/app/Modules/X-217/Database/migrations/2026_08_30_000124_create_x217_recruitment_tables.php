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
        if (! Schema::hasTable('affiliate_prospects')) {
            Schema::create('affiliate_prospects', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('partner_name');
                $table->string('email');
                $table->string('stage')->default('discovered'); // discovered, pitched, negotiating, accepted, declined
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('recruitment_offers')) {
            Schema::create('recruitment_offers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('prospect_id')->constrained('affiliate_prospects')->cascadeOnDelete();
                $table->unsignedInteger('offered_rate_bps'); // TEST ANCHOR: lands in X-205 with exact terms
                $table->unsignedInteger('ceiling_rate_bps')->default(2500); // TEST ANCHOR: no offer exceeds ceiling
                $table->text('terms_summary');
                $table->boolean('is_accepted')->default(false);
                $table->timestamp('accepted_at')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['affiliate_prospects', 'recruitment_offers'];

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
        Schema::dropIfExists('recruitment_offers');
        Schema::dropIfExists('affiliate_prospects');
    }
};
