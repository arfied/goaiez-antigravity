<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webstudio_sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('source', 16)->default('clone');            // clone | template
            $table->foreignId('site_clone_job_id')->nullable()->constrained('site_clone_jobs')->nullOnDelete();
            $table->string('project_id', 64);                            // the Webstudio project uuid
            $table->text('editor_token');                                // encrypted by the model
            $table->string('title', 255);
            $table->string('publish_status', 16)->default('never');      // never | publishing | published | failed
            $table->string('publish_message', 255)->default('Not published yet');
            $table->text('publish_error')->nullable();                   // internal only — no view renders it
            $table->timestamp('publish_started_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('deployment_id')->nullable();     // X-157 deployments.id, no FK across the module seam
            $table->string('deploy_hash', 64)->nullable();
            $table->timestamps();
            $table->unique('project_id');
            $table->index(['business_id', 'publish_status']);
        });

        $table = 'webstudio_sites';
        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");

        DB::statement(<<<SQL
            CREATE POLICY tenant_isolation ON {$table}
                USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webstudio_sites');
    }
};
