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
        if (! Schema::hasTable('credit_ledger_entries')) {
            Schema::create('credit_ledger_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('entry_type'); // debit, grant, topup, refund
                $table->bigInteger('amount_hundredths_cents'); // integer hundredths of a cent (§18)
                $table->bigInteger('balance_after_hundredths_cents');
                $table->string('reference_id')->index();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });

            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_credit_ledger_update() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'credit_ledger_entries is append-only and has no UPDATE path';
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS trg_prevent_update_credit_ledger ON credit_ledger_entries;
                CREATE TRIGGER trg_prevent_update_credit_ledger
                BEFORE UPDATE ON credit_ledger_entries
                FOR EACH ROW EXECUTE FUNCTION prevent_credit_ledger_update();
            SQL);
        }

        if (! Schema::hasTable('meters')) {
            Schema::create('meters', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('meter_type')->index(); // ai_seconds, sms_segments, voice_minutes
                $table->bigInteger('units_used')->default(0);
                $table->bigInteger('cost_hundredths_cents')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('trial_limits')) {
            Schema::create('trial_limits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedInteger('included_minutes')->default(100);
                $table->unsignedInteger('rate_cents_per_min')->default(7);
                $table->bigInteger('daily_topup_ceiling_cents')->default(50000); // $500.00 daily ceiling
                $table->bigInteger('topups_today_cents')->default(0);
                $table->date('last_topup_date')->nullable();
                $table->bigInteger('current_balance_hundredths_cents')->default(1000000); // $100.00 initial
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dunning_states')) {
            Schema::create('dunning_states', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->unsignedSmallInteger('day_in_cycle')->default(0);
                $table->string('status')->default('active'); // active, warning, banner, grace, ai_off_voicemail_only, terminated
                $table->boolean('ai_enabled')->default(true);
                $table->boolean('phone_answering')->default(true);
                $table->boolean('voicemail_only')->default(false);
                $table->timestamps();
            });
        }

        $tables = ['credit_ledger_entries', 'meters', 'trial_limits', 'dunning_states'];

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
        Schema::dropIfExists('dunning_states');
        Schema::dropIfExists('trial_limits');
        Schema::dropIfExists('meters');
        Schema::dropIfExists('credit_ledger_entries');
    }
};
