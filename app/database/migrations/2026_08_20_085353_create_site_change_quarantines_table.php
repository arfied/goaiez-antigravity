<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A fix that made one site worse, resting on that site — `BUILD-PLAN` §2.11.3
 * slice H: *"quarantine of that fix/automation for that site (a sick change
 * rests; the automation is not retried onto the same site)"*.
 *
 * ⛔ **THE SUBJECT IS A PAIR, `(location, change_type)`, AND NEITHER HALF ALONE
 * IS RIGHT.** Quarantining the *automation* platform-wide would stop every
 * tenant on one tenant's bad afternoon — 3780's shape, where a containment sized
 * for the wrong population silences everybody. Quarantining the *change set*
 * would stop nothing, because the next run writes a new one. What made this
 * site worse is this kind of change on this site.
 *
 * ⛔ **IT DOES NOT RELEASE ITSELF, AND THAT IS THE WHOLE OF IT.** `BUILD-PLAN`
 * §2.11.3's L row asks for *"an Ops release command"*, and 3780–3796 is what
 * happens when the after-the-trigger behaviour is left unscoped: a quarantine
 * with no exit is a tenant stuck for ever, and one that lifts on a timer is not
 * a quarantine. `actuation:release-quarantine` is the door, it requires a typed
 * reason and a named actor, and `NumberStateCommand` is the precedent for both.
 *
 * ⚠️ **THE PARTIAL UNIQUE INDEX IS WHAT MAKES "QUARANTINED" A STATE RATHER THAN
 * A LOG.** One live row per pair; released rows accumulate as history. Without
 * it a second regression on the same fix would open a second row, and releasing
 * one of them would leave the pair quarantined by the other with nothing on any
 * screen explaining why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_change_quarantines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Location-scoped, because the website is a location's
            // (`locations.website_url`) and `CLAUDE.md` requires every job be
            // location-scoped. A business-wide rest would stop a fix on a site
            // that never had it applied.
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            // The same free string as `site_changes.change_type`, and
            // deliberately not an enum for the same reason: the vocabulary
            // grows with every fix slice, and a closed set here would need a
            // migration per fix.
            $table->string('change_type', 64);

            // The change whose measurement caused this. Nullable because an Ops
            // quarantine typed by a person answers to no row, and because a
            // cascade from a deleted location would otherwise take the history
            // with it while the location survives.
            $table->foreignId('site_change_id')->nullable()->constrained('site_changes')->nullOnDelete();

            $table->timestamp('quarantined_at');

            // 'autopilot' | 'owner' | 'staff', cast to App\Enums\SiteChangeActor
            // — the same vocabulary `site_changes.rolled_back_by` uses, because
            // the question is the same one: whose call was this (5524).
            $table->string('quarantined_by', 16);
            $table->string('quarantined_reason', 255);

            // ⚠️ **THE RELEASE IS THREE FACTS OR NONE**, on
            // `site_changes_rollback_is_all_or_nothing`'s precedent (5524): a
            // row that says it was released and cannot say by whom is what the
            // constraint exists to prevent, and this one is an operator putting
            // an automation back onto a customer's website.
            $table->timestamp('released_at')->nullable();
            $table->string('released_by', 64)->nullable();
            $table->string('released_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['business_id', 'location_id']);
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX site_change_quarantines_one_live_per_fix
                ON site_change_quarantines (business_id, location_id, change_type)
                WHERE released_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE site_change_quarantines
                ADD CONSTRAINT site_change_quarantines_release_is_all_or_nothing CHECK (
                    (released_at IS NULL AND released_by IS NULL AND released_reason IS NULL)
                    OR (released_at IS NOT NULL AND released_by IS NOT NULL AND released_reason IS NOT NULL)
                )
        SQL);

        DB::statement('ALTER TABLE site_change_quarantines ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE site_change_quarantines FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON site_change_quarantines
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_change_quarantines');
    }
};
