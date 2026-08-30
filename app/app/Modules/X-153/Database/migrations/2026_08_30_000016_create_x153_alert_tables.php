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
        if (! Schema::hasTable('alerts')) {
            Schema::create('alerts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('alert_class')->default('account'); // account, missed_call, urgent
                $table->string('title');
                $table->text('body');
                $table->string('status')->default('pending'); // pending, claimed, expired
                $table->timestamp('claim_expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('reply_codes')) {
            Schema::create('reply_codes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('alert_id')->constrained('alerts')->cascadeOnDelete();
                $table->string('code', 20)->index();
                $table->boolean('is_live')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('alert_claims')) {
            Schema::create('alert_claims', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('alert_id')->constrained('alerts')->cascadeOnDelete();
                $table->unsignedBigInteger('claimed_by_user_id');
                $table->timestamp('claimed_at')->useCurrent();
                $table->string('status')->default('active'); // active, expired
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['alerts', 'reply_codes', 'alert_claims'];

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
        Schema::dropIfExists('alert_claims');
        Schema::dropIfExists('reply_codes');
        Schema::dropIfExists('alerts');
    }
};
