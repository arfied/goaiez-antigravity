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
        if (! Schema::hasTable('visits')) {
            Schema::create('visits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('visitor_id')->index();
                $table->string('ip_hash')->nullable();
                $table->text('user_agent')->nullable();
                $table->string('utm_source')->nullable();
                $table->string('utm_medium')->nullable();
                $table->string('utm_campaign')->nullable();
                $table->string('landing_page')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('visitor_sessions')) {
            Schema::create('visitor_sessions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
                $table->string('session_token')->index();
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pixel_events')) {
            Schema::create('pixel_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('session_id')->constrained('visitor_sessions')->cascadeOnDelete();
                $table->string('event_name')->index();
                $table->jsonb('payload');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cwv_samples')) {
            Schema::create('cwv_samples', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedInteger('lcp_ms')->default(0);
                $table->unsignedInteger('fid_ms')->default(0);
                $table->float('cls_score')->default(0.0);
                $table->string('url')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('identity_links')) {
            Schema::create('identity_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('visitor_id')->index();
                $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
                $table->string('identity_type')->default('cookie_less_first_party');
                $table->timestamps();
            });
        }

        $tables = ['visits', 'visitor_sessions', 'pixel_events', 'cwv_samples', 'identity_links'];

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
        Schema::dropIfExists('identity_links');
        Schema::dropIfExists('cwv_samples');
        Schema::dropIfExists('pixel_events');
        Schema::dropIfExists('visitor_sessions');
        Schema::dropIfExists('visits');
    }
};
