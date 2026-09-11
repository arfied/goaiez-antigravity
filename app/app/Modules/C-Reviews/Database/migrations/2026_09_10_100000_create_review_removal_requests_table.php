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
        Schema::create('review_removal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('review_request_id')->constrained('review_requests')->cascadeOnDelete();
            $table->string('google_review_id')->nullable();
            $table->string('tos_ground')->nullable();
            $table->text('prepared_body')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('status')->default('prepared');
            $table->timestamps();
        });

        $table = 'review_removal_requests';
        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");

        DB::statement(<<<SQL
            CREATE POLICY tenant_isolation ON {$table}
                USING (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        $table = 'review_removal_requests';
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
        DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        Schema::dropIfExists($table);
    }
};
