<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical answers the bot may state as fact (DATA-MODEL §5.9).
 *
 * verified_by_owner is the gate between 'we inferred this from your site' and
 * 'the owner confirmed it'. The consistency engine repairs against these, so an
 * unverified fact must never be presented as canonical.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_facts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            $table->string('key');
            $table->text('value')->nullable();
            $table->string('source')->nullable();
            $table->boolean('verified_by_owner')->default(false);

            $table->timestamp('updated_at')->nullable();

            // One value per key per location, and composite so two businesses
            // can hold the same key.
            $table->unique(['business_id', 'location_id', 'key']);
        });

        DB::statement('ALTER TABLE business_facts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE business_facts FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON business_facts
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('business_facts');
    }
};
