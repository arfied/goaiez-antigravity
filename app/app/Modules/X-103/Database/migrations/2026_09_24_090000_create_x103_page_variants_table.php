<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('page_variants')) {
            return;
        }

        Schema::create('page_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->text('control_headline');
            $table->text('variant_headline');
            $table->string('control_deploy_hash')->nullable();
            $table->string('variant_deploy_hash')->nullable();
            $table->string('control_commit_id')->nullable();
            $table->string('variant_commit_id')->nullable();
            $table->string('status')->default('running'); // running|stopped|frozen
            $table->timestamp('started_at');
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'page_id', 'status']);
        });

        DB::statement('ALTER TABLE page_variants ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE page_variants FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON page_variants');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON page_variants
                USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }
};
