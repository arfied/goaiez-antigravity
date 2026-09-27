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
        Schema::create('site_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('code');
            $table->text('text');
            $table->string('status')->default('pending');
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->unique(['business_id', 'code']);
        });

        DB::statement('ALTER TABLE site_recommendations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE site_recommendations FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON site_recommendations');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON site_recommendations
                USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON site_recommendations');
        Schema::dropIfExists('site_recommendations');
    }
};
