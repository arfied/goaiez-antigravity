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
        if (! Schema::hasTable('approval_chains')) {
            Schema::create('approval_chains', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('name');
                $table->unsignedSmallInteger('steps_count')->default(1);
                $table->jsonb('chain_config');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('approval_items')) {
            Schema::create('approval_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('approval_chain_id')->nullable()->constrained('approval_chains')->nullOnDelete();
                $table->string('item_type')->index();
                $table->string('subject');
                $table->jsonb('payload');
                $table->string('autonomy_level')->default('L2'); // L1, L2, L3
                $table->boolean('is_l1_forever')->default(false);
                $table->unsignedSmallInteger('current_step')->default(1);
                $table->string('status')->default('pending'); // pending, approved, rejected, expired, escalated
                $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->text('decision_comment')->nullable();
                $table->timestamp('expires_at');
                $table->string('magic_token')->nullable()->index();
                $table->timestamps();
            });
        }

        $tables = ['approval_chains', 'approval_items'];

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
        Schema::dropIfExists('approval_items');
        Schema::dropIfExists('approval_chains');
    }
};
