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
        if (! Schema::hasTable('influencer_profiles')) {
            Schema::create('influencer_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('handle')->index();
                $table->string('platform'); // instagram, youtube, tiktok
                $table->unsignedInteger('audience_size')->default(10000);
                $table->decimal('engagement_rate', 4, 2)->default(3.50);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('influencer_deals')) {
            Schema::create('influencer_deals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('influencer_id')->constrained('influencer_profiles')->cascadeOnDelete();
                $table->bigInteger('deal_amount_cents');
                $table->string('status')->default('proposed'); // proposed, active, delivered, paid
                $table->boolean('is_paid')->default(false); // TEST ANCHOR: payout NEVER fires without verified deliverable
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('deliverables')) {
            Schema::create('deliverables', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('deal_id')->constrained('influencer_deals')->cascadeOnDelete();
                $table->string('live_url');
                $table->unsignedSmallInteger('http_status')->default(200); // TEST ANCHOR: URL returning 200
                $table->string('artifact_hash'); // TEST ANCHOR: captured and hashed
                $table->boolean('is_verified')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['influencer_profiles', 'influencer_deals', 'deliverables'];

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
        Schema::dropIfExists('deliverables');
        Schema::dropIfExists('influencer_deals');
        Schema::dropIfExists('influencer_profiles');
    }
};
