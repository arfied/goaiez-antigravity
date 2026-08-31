<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Embeddable widgets (DATA-MODEL §5.11).
 *
 * embed_key is GLOBALLY unique on purpose — the one exception pattern, same
 * as businesses.pixel_tenant_id: it is an opaque public join key that the
 * embed script presents *before* any tenant is known, so it cannot be
 * composite. It must therefore always be random and meaningless (a UUID),
 * never derived from tenant data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();

            // review_widget|badge|feedback_form|…
            $table->string('type');

            $table->uuid('embed_key')->unique();

            $table->jsonb('config')->nullable();

            // TEXT[] in DATA-MODEL; jsonb list. The embed refuses to render on
            // origins outside this list.
            $table->jsonb('allowed_domains')->nullable();

            $table->string('version')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index('business_id');
        });

        DB::statement('ALTER TABLE plugins ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE plugins FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON plugins
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};
