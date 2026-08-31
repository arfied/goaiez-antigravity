<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop `review_phi_consents` — the owner retired `29` §2 rule 24 and its §12.1
 * line on 2026-08-30 (12532, 12540), and asked directly whether 2079–2081's
 * per-review consent went with it. **It does**: *"yes kill the review phi
 * consent too"*.
 *
 * ⛔ **THIS DESTROYS STORED CONSENT RECORDS AND THERE IS NO WAY BACK.** Every
 * row here is a named reviewer's undertaking — a wording version, a timestamp, a
 * URL, a hashed address and a user agent — captured on a public page. `down()`
 * rebuilds the table and cannot rebuild the rows.
 *
 * ⚠️ **THE MOMENT IT HAPPENS IS A DEPLOYMENT, WHICH IS THE OWNER'S TO PICK.**
 * `composer deploy` runs `migrate --force`; nothing about merging this commit
 * removes a row. That is deliberate — a lane may write the migration and may not
 * choose the hour it runs.
 *
 * ⛔ **AND THE ALTERNATIVE WAS CONSIDERED AND REFUSED, SO NOBODY HAS TO REDO
 * THAT REASONING.** Leaving the table behind with its code gone would have made
 * it a tenant-owned, RLS-enforced relation with no model, no reader and no
 * writer — 272's shape, needing an `$exempt` entry in
 * `tests/Feature/Architecture/TenancyTest.php` justified by nothing but
 * reluctance. **A table kept "just in case" that no code can read is not
 * retention, it is an unmarked grave**, and the honest choice is to drop it
 * deliberately or keep it deliberately. The ruling says drop.
 *
 * ⚠️ **`audit_log` IS NOT TOUCHED.** Every `record()` call wrote a
 * `review.phi_analysis_consented` row there, and that table is append-only and
 * outside this migration's subject — so the fact that a consent was taken
 * survives even though its evidence does not. **That is a weaker record and it
 * is stated rather than relied on**: the audit row names the review and the
 * actor, not the wording the reviewer agreed to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('review_phi_consents');
    }

    /**
     * ⚠️ **THE SHAPE COMES BACK AND THE ROWS DO NOT**, which is the honest
     * `down()` for a drop. It is written out rather than left unimplemented so a
     * rollback leaves a schema the rest of the suite can migrate through, and it
     * mirrors the creating migration's own tenancy rules — `ENABLE` + `FORCE`
     * row-level security and a policy in the same migration, because a
     * tenant-owned table without both is decoration.
     */
    public function down(): void
    {
        Schema::create('review_phi_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->string('disclosure_version');
            $table->timestamp('consented_at');
            $table->string('source_url');
            $table->string('ip_hash');
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique('review_id');
        });

        DB::statement('ALTER TABLE review_phi_consents ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE review_phi_consents FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON review_phi_consents
                USING (business_id = current_setting('app.business_id', true)::bigint)
                WITH CHECK (business_id = current_setting('app.business_id', true)::bigint)
        SQL);
    }
};
