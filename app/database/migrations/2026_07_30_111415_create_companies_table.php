<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM companies — the B2B parent a customer can belong to (DATA-MODEL §5.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('domain')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->jsonb('custom_fields')->default('{}');

            $table->timestamps();

            $table->index(['business_id', 'name']);
        });

        DB::statement('ALTER TABLE companies ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE companies FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON companies
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
