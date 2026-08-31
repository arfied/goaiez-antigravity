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
        if (! Schema::hasTable('device_tokens')) {
            Schema::create('device_tokens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('person_id')->nullable()->index();
                $table->string('platform')->default('ios'); // ios, android, web
                $table->string('device_token')->index();
                $table->string('status')->default('active'); // active, retired
                $table->string('retirement_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('push_deliveries')) {
            Schema::create('push_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('device_token_id')->constrained('device_tokens')->cascadeOnDelete();
                $table->jsonb('payload'); // Sanitized push payload (TEST ANCHOR)
                $table->boolean('sanitized')->default(true);
                $table->string('status')->default('delivered');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('push_prompts')) {
            Schema::create('push_prompts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('prompt_title');
                $table->text('prompt_body');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $tables = ['device_tokens', 'push_deliveries', 'push_prompts'];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'business_id')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('push_prompts');
        Schema::dropIfExists('push_deliveries');
        Schema::dropIfExists('device_tokens');
    }
};
