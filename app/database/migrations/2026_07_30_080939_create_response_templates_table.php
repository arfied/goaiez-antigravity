<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reply templates a business curates for AI-drafted responses
 * (DATA-MODEL §5.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('response_templates', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->text('body');

            // Nullable, unlike autopilot_settings.brand_voice: a template
            // without a voice inherits the location's setting.
            $table->string('brand_voice')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['business_id', 'is_active']);
        });

        DB::statement('ALTER TABLE response_templates ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE response_templates FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON response_templates
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('response_templates');
    }
};
