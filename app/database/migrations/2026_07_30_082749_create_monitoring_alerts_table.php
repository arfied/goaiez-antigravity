<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Something the watchers found (DATA-MODEL §5.12): a GBP unauthorised edit,
 * a suspension, downtime, a review-velocity drop. Severity is information
 * carried by the row, never by colour alone (`22`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_alerts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('alert_type');
            $table->string('severity')->nullable();

            $table->string('title');
            $table->jsonb('detail')->nullable();

            $table->timestamp('notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamp('created_at')->nullable();

            // The hot query is "open alerts for this business".
            $table->index(['business_id', 'resolved_at']);
        });

        DB::statement('ALTER TABLE monitoring_alerts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE monitoring_alerts FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON monitoring_alerts
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_alerts');
    }
};
