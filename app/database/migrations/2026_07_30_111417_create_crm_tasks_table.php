<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Human follow-up tasks against a customer (DATA-MODEL §5.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->timestamp('due_at')->nullable();
            $table->string('status')->default('open');

            $table->timestamp('created_at')->nullable();

            $table->index(['business_id', 'status', 'due_at']);
        });

        DB::statement('ALTER TABLE crm_tasks ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE crm_tasks FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON crm_tasks
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
    }
};
