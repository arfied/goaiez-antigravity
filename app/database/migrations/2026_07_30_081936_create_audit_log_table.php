<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The compliance record (DATA-MODEL §5.12). Every sensitive action lands
 * here (`29` §2 rule 30).
 *
 * APPEND-ONLY (§5.14): updates and deletes are blocked at the model layer —
 * see AuditLogEntry. An audit trail that can be edited is not an audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // An actor label, not a users FK: automations, the owner replying
            // by text, and staff all act here. Cross-tenant admin access also
            // writes a row with the acting agent's identity (`29` §2 rule 42).
            $table->string('actor');

            $table->string('action');

            $table->nullableMorphs('entity');

            $table->jsonb('metadata')->nullable();

            $table->timestamp('created_at')->nullable();
        });

        DB::statement(
            'CREATE INDEX idx_audit_business_created
                 ON audit_log (business_id, created_at DESC)'
        );

        DB::statement('ALTER TABLE audit_log ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE audit_log FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON audit_log
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
