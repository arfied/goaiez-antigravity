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
        if (! Schema::hasTable('suppressions')) {
            Schema::create('suppressions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('recipient_phone')->index();
                $table->string('channel')->default('sms');
                $table->string('reason');
                $table->timestamp('suppressed_at')->useCurrent();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('send_permits')) {
            Schema::create('send_permits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('recipient_phone')->index();
                $table->string('channel')->default('sms');
                $table->string('permit_status'); // granted, refused
                $table->string('refusal_reason')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('import_attestations')) {
            Schema::create('import_attestations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('attestation_hash')->index();
                $table->string('source')->default('csv_import');
                $table->unsignedInteger('contacts_count')->default(0);
                $table->string('attested_by')->default('owner');
                $table->timestamp('attested_at')->useCurrent();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('compliance_registers')) {
            Schema::create('compliance_registers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('register_name');
                $table->string('status')->default('compliant');
                $table->jsonb('slot_states')->nullable();
                $table->timestamps();
            });
        }

        $tables = ['suppressions', 'send_permits', 'import_attestations', 'compliance_registers'];

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
        Schema::dropIfExists('compliance_registers');
        Schema::dropIfExists('import_attestations');
        Schema::dropIfExists('send_permits');
        Schema::dropIfExists('suppressions');
    }
};
