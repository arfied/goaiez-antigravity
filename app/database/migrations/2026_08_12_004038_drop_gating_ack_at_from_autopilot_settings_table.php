<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The review-gating acknowledgement is removed — decisions 2074 and 2660.
 *
 * WHAT THIS COLUMN DID. `ReviewRouter` read it as permission to apply an invite
 * threshold at all: `$thresholdsApply = $settings?->gating_ack_at !== null`, and
 * when it was null every enabled destination was offered to every rating. That
 * was `29` §12.1's second build-failing subject for row 3 and it failed *open*
 * on purpose — gating without a recorded acknowledgement was judged the larger
 * exposure, so the safe failure was the ungated one (114, 290, 374).
 *
 * ⚠️ **AND THE FAIL-OPEN BRANCH WAS THE ONLY ONE THAT EVER RAN IN PRODUCTION.**
 * `TenantProvisioner` leaves the column null by design and `ReviewGating` — the
 * one writer, shipped at 520 — is reached only by an owner completing COMP-02's
 * screen. So the platform default of 4 reached nobody, and the per-destination
 * threshold design of 111/1186 was inert. That is what makes dropping this
 * column a behaviour change rather than a tidy-up, and the change is the
 * ruling's whole point.
 *
 * ⚠️ **THE OWNER RULED WITH THE COST IN FRONT OF THEM.** The acknowledgement was
 * the single stored record that a business chose star-thresholded invitation
 * knowingly, and it is the artefact a platform or a regulator would have asked
 * to see. `CHANGES-TO-CONFIRM.md` §7 put that trade in front of them and 2074
 * records the answer. **The build-failing test it carried is replaced rather
 * than deleted** (2075, 1161's route) — see
 * `tests/Feature/ReviewRoutingGateTest.php`, whose new subject is that every
 * rating is captured and kept, and none is ever deleted, suppressed or hidden.
 *
 * ⚠️ **IRREVERSIBLE IN THE ONLY SENSE THAT MATTERS, AND `down()` SAYS SO RATHER
 * THAN PRETENDING.** Re-adding a nullable timestamp restores the shape and not
 * one acknowledgement — every row comes back null, which is the state the router
 * used to read as "gate nobody". So a rollback does not merely undo this: it
 * silently un-gates every tenant. The column is restored because a migration
 * that cannot roll back blocks the ones after it, and the consequence is written
 * here because nothing else would say it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('autopilot_settings', function (Blueprint $table): void {
            $table->dropColumn('gating_ack_at');
        });
    }

    public function down(): void
    {
        Schema::table('autopilot_settings', function (Blueprint $table): void {
            $table->timestamp('gating_ack_at')->nullable();
        });
    }
};
