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
        if (! Schema::hasTable('form_definitions')) {
            Schema::create('form_definitions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('form_name');
                $table->string('slug')->index();
                $table->jsonb('steps'); // multi-step form logic (G2-17)
                $table->jsonb('schema');
                $table->string('honeypot_field')->default('website_url'); // G3-64, G13-05
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('form_submissions')) {
            Schema::create('form_submissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('form_definition_id')->constrained('form_definitions')->cascadeOnDelete();
                $table->foreignId('person_id')->constrained('people')->cascadeOnDelete(); // TEST ANCHOR: every submission references Person id
                $table->jsonb('payload');
                $table->string('ip_address')->nullable();
                $table->string('user_timezone')->nullable();
                $table->boolean('is_spam')->default(false);
                $table->string('spam_reason')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['form_definitions', 'form_submissions'];

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
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_definitions');
    }
};
