<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Setup-wizard state (DATA-MODEL §5.12).
 *
 * Tenant-owned, like every other table here. That is worth stating explicitly
 * because it was briefly built the other way, on the reasoning that the wizard
 * runs before a business exists and therefore cannot carry a tenant key.
 *
 * The signup order in `29` §6.2 settles it:
 *
 *     POST /register -> tenant auto-provision -> wizard
 *
 * The business is provisioned *before* the wizard runs, so business_id is
 * available and NOT NULL. Genuinely pre-signup state lives in `public_audits`,
 * which has no tenant by design; `/start?audit={token}` copies its findings into
 * this table afterwards, by which point the tenant exists.
 *
 * Getting that ordering wrong is expensive in one direction only: a table left
 * outside the boundary has no second layer and no test that notices, whereas a
 * tenant key that turns out to be unavailable fails loudly on the first insert.
 *
 * See docs/DECISIONS.md 177.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wizard_progress', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->integer('current_step')->default(1);
            $table->boolean('completed')->default(false);
            $table->smallInteger('setup_score')->nullable();

            // Carries the audit pre-fill: business name, findings, categories
            // (`29` §6.2). Tenant-identifying, which is the other reason this
            // table belongs inside the boundary rather than beside it.
            $table->jsonb('data')->default('{}');

            $table->timestamps();

            // Composite rather than the spec's bare UNIQUE(user_id). A global
            // unique on user_id would let one business's row block another's for
            // the same person — and the failure would reveal that the row exists,
            // which is the disclosure the composite-unique rule exists to
            // prevent. One wizard per user per business also survives agency
            // mode, where a person legitimately holds more than one.
            $table->unique(['business_id', 'user_id']);
        });

        DB::statement('ALTER TABLE wizard_progress ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE wizard_progress FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON wizard_progress
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('wizard_progress');
    }
};
