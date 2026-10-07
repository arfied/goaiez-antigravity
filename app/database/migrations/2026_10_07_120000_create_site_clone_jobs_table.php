<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_clone_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('host');
            $table->string('slug');
            $table->string('status')->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('message', 255)->default('Queued');
            $table->jsonb('logs')->nullable();
            $table->text('error')->nullable();
            $table->text('internal_error')->nullable();
            $table->string('work_dir')->nullable();
            $table->integer('pid')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('webstudio_project_id')->nullable();
            $table->text('editor_token')->nullable();
            $table->decimal('cost_usd', 8, 4)->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });

        $table = 'site_clone_jobs';
        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");

        DB::statement(<<<SQL
            CREATE POLICY tenant_isolation ON {$table}
                USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);

        DB::statement("CREATE UNIQUE INDEX site_clone_jobs_one_active_per_business ON site_clone_jobs (business_id) WHERE status IN ('queued','running')");
        DB::statement("CREATE UNIQUE INDEX site_clone_jobs_one_active_per_user ON site_clone_jobs (user_id) WHERE status IN ('queued','running')");
    }

    public function down(): void
    {
        Schema::dropIfExists('site_clone_jobs');
    }
};
