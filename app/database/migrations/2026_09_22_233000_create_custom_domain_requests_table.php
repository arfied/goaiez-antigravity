<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_domain_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->string('status')->default('requested');
            $table->timestamp('requested_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'domain']);
        });

        DB::statement('ALTER TABLE custom_domain_requests ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE custom_domain_requests FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON custom_domain_requests
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_domain_requests');
    }
};
