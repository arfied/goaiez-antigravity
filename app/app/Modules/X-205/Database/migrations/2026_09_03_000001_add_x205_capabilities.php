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
        Schema::table('affiliates', function (Blueprint $table) {
            $table->boolean('w9_on_file')->default(false);
            $table->bigInteger('w9_threshold_cents')->nullable();
        });

        Schema::table('affiliate_attributions', function (Blueprint $table) {
            $table->string('fraud_review_status')->default('none'); // none, proposed, approved
            $table->string('triggering_dispute_ref')->nullable(); // For clawbacks
        });

        if (! Schema::hasTable('referral_clicks')) {
            Schema::create('referral_clicks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
                $table->string('visitor_id')->index();
                $table->string('utm_source')->nullable();
                $table->string('utm_medium')->nullable();
                $table->string('utm_campaign')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('affiliate_tiers')) {
            Schema::create('affiliate_tiers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->integer('min_referrals');
                $table->unsignedInteger('commission_rate_bps');
                $table->timestamps();
            });
        }

        $tables = ['referral_clicks', 'affiliate_tiers'];

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
        Schema::dropIfExists('affiliate_tiers');
        Schema::dropIfExists('referral_clicks');
        Schema::table('affiliate_attributions', function (Blueprint $table) {
            $table->dropColumn('triggering_dispute_ref');
            $table->dropColumn('fraud_review_status');
        });
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropColumn('w9_threshold_cents');
            $table->dropColumn('w9_on_file');
        });
    }
};
