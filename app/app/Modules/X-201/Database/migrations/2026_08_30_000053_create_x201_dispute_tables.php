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
        if (! Schema::hasTable('disputes')) {
            Schema::create('disputes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->unsignedInteger('chargeback_amount_cents');
                $table->string('reason')->default('fraudulent');
                $table->string('status')->default('opened'); // opened, compiled, submitted, won, lost
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dispute_evidence')) {
            Schema::create('dispute_evidence', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
                $table->string('evidence_type'); // signature, signed_estimate, invoice, call_log
                $table->text('file_url_or_content');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dispute_outcomes')) {
            Schema::create('dispute_outcomes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
                $table->string('outcome'); // won, lost (TEST ANCHOR)
                $table->string('lost_reason')->nullable();
                $table->boolean('commission_clawback_triggered')->default(false); // TEST ANCHOR
                $table->timestamps();
            });
        }

        $tables = ['disputes', 'dispute_evidence', 'dispute_outcomes'];

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
        Schema::dropIfExists('dispute_outcomes');
        Schema::dropIfExists('dispute_evidence');
        Schema::dropIfExists('disputes');
    }
};
