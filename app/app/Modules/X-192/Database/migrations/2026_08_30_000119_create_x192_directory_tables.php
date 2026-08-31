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
        if (! Schema::hasTable('directory_memberships')) {
            Schema::create('directory_memberships', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('directory_name');
                $table->string('directory_url');
                $table->boolean('is_noindex')->default(false); // TEST ANCHOR: noindex penalized & notes Google can't see this
                $table->unsignedSmallInteger('rank_score')->default(50);
                $table->string('recommendation_note')->nullable();
                $table->boolean('is_purchased')->default(false); // TEST ANCHOR: no purchase without approval action row
                $table->unsignedBigInteger('approved_by_action_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('citations')) {
            Schema::create('citations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('membership_id')->nullable()->constrained('directory_memberships')->nullOnDelete();
                $table->string('nap_business_name');
                $table->string('nap_phone');
                $table->string('nap_address');
                $table->boolean('is_verified')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['directory_memberships', 'citations'];

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
        Schema::dropIfExists('citations');
        Schema::dropIfExists('directory_memberships');
    }
};
