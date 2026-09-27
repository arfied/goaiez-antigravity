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
        Schema::create('whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('provider')->default('zernio');
            $table->string('provider_profile_ref')->nullable();
            $table->string('account_ref')->nullable()->unique();
            $table->string('display_label')->nullable();
            $table->string('status')->default('pending');
            $table->string('last_error', 255)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();
        });

        $table = 'whatsapp_connections';
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
        Schema::dropIfExists('whatsapp_connections');
    }
};
