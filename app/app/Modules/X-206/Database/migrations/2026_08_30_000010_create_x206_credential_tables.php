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
        if (! Schema::hasTable('credentials')) {
            Schema::create('credentials', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('service_name')->index();
                $table->text('encrypted_secret');
                $table->string('key_hint')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('credential_reveals')) {
            Schema::create('credential_reveals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('credential_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('service_name');
                $table->string('ip_address')->nullable();
                $table->string('status'); // permitted, refused
                $table->string('refusal_reason')->nullable();
                $table->timestamp('revealed_at')->useCurrent();
                $table->timestamps();
            });
        }

        $tables = ['credentials', 'credential_reveals'];

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
        Schema::dropIfExists('credential_reveals');
        Schema::dropIfExists('credentials');
    }
};
