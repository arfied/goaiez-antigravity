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
        if (! Schema::hasTable('campaign_steps')) {
            Schema::create('campaign_steps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('campaign_id')->index();
                $table->unsignedSmallInteger('step_number');
                $table->string('channel'); // email, sms, voice
                $table->string('template_name');
                $table->unsignedInteger('delay_days')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('campaign_runs')) {
            Schema::create('campaign_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('campaign_id')->index();
                $table->unsignedBigInteger('person_id')->index();
                $table->unsignedSmallInteger('current_step')->default(1);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_suppressed')->default(false); // TEST ANCHOR: open RECOVER suppresses sequence
                $table->string('suppression_reason')->nullable();
                $table->string('stopped_reason')->nullable(); // TEST ANCHOR: reply on ANY channel stops sequence
                $table->timestamps();
            });
        }

        $tables = ['campaign_steps', 'campaign_runs'];

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
        Schema::dropIfExists('campaign_runs');
        Schema::dropIfExists('campaign_steps');
    }
};
