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
        if (! Schema::hasTable('contact_tags')) {
            Schema::create('contact_tags', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('contact_id')->constrained('people')->cascadeOnDelete();
                $table->string('tag')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('custom_fields')) {
            Schema::create('custom_fields', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('entity_type')->default('person');
                $table->unsignedBigInteger('entity_id');
                $table->string('field_key')->index();
                $table->text('field_value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('lead_scores')) {
            Schema::create('lead_scores', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
                $table->integer('score')->default(50);
                $table->string('grade', 5)->default('B');
                $table->float('confidence')->default(1.0);
                $table->jsonb('signals')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('takeover_latches')) {
            Schema::create('takeover_latches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('conversation_id')->index();
                $table->unsignedBigInteger('operator_id');
                $table->string('operator_name');
                $table->boolean('is_active')->default(true);
                $table->timestamp('latched_at')->useCurrent();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['contact_tags', 'custom_fields', 'lead_scores', 'takeover_latches'];

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
        Schema::dropIfExists('takeover_latches');
        Schema::dropIfExists('lead_scores');
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('contact_tags');
    }
};
