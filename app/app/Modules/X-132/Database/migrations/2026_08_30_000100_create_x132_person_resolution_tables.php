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
        if (! Schema::hasTable('person_links')) {
            Schema::create('person_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('canonical_person_id')->index();
                $table->unsignedBigInteger('linked_person_id')->index();
                $table->decimal('confidence_score', 4, 3)->default(1.000);
                $table->string('match_tier')->default('tier_1_exact_email'); // 6-tier waterfall (G13-25)
                $table->timestamps();

                $table->unique(['business_id', 'canonical_person_id', 'linked_person_id']);
            });
        }

        if (! Schema::hasTable('resolution_evidence')) {
            Schema::create('resolution_evidence', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('canonical_person_id')->index();
                $table->string('field_name');
                $table->string('field_value');
                $table->string('source_provider'); // email_match, phone_e164, cookie_sync, ip_reveal
                $table->decimal('confidence_score', 4, 3)->default(1.000);
                $table->timestamps();
            });
        }

        $tables = ['person_links', 'resolution_evidence'];

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
        Schema::dropIfExists('resolution_evidence');
        Schema::dropIfExists('person_links');
    }
};
