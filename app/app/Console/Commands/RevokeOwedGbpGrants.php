<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\GbpProvider;
use App\Enums\OperatorAlertKind;
use App\Services\Gbp\GbpConnections;
use App\Services\Ops\OperatorAlerts;
use App\Services\Tenant\TenantDeletion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * End the Google grants that outlived the customers who gave them.
 *
 * ⚠️ **THIS IS THE WRITER 4731 SAID WAS MISSING** (272's shape, and this file is
 * the reason `revocation_owed_at` is not another column nothing reads).
 * {@see TenantDeletion::execute()} revokes at the moment of erasure and stamps
 * the binding first, so the ordinary path never reaches here. This exists for the
 * day it fails: Zernio down, our key rotated, or `gbp.zernio_enabled` switched
 * off during an incident. Without it, one failed HTTP call would leave a
 * subprocessor holding `business.manage` — read **and write** — on a former
 * customer's Google Business Profile permanently, which is the whole defect
 * rather than a corner of it.
 *
 * ⚠️ **NO TENANT, AND NONE IS NEEDED.** `gbp_account_bindings` is the
 * platform-scoped index (4721): no RLS, no `BelongsToTenant`, and it is the only
 * table in this system that still names the accounts of a business that no longer
 * exists. Nothing here walks owners, and nothing here may start to.
 *
 * ## ⚠️ `--reconcile` IS A REPORT AND REVOKES NOTHING
 *
 * Bindings stamped by the deletion path are ours by record. Bindings whose
 * business vanished **before** this slice existed are ours by *inference*, and
 * the inference — asking, one binding at a time from inside its own tenancy,
 * whether the business is still there — is sound but is still an inference. A
 * sweep that revoked on it would disconnect a live customer's Google listing the
 * first time a filter is wrong, and the owner cannot put it back without
 * consenting again at Zernio. So the flag prints a count and a set of business
 * references, and a human decides what to do about them (4884).
 *
 * ⚠️ It is off by default and deliberately not scheduled: the probe is O(one
 * tenancy switch per binding), and the scheduled run has no reader for its
 * output.
 *
 * ⛔ **THE SECOND HALF OF THAT SENTENCE STOPPED BEING TRUE OF THIS COMMAND ON
 * 2026-08-21 AND STAYS TRUE OF `--reconcile` — BOTH READINGS KEPT** (7020). The
 * scheduled run now has a reader: the section below. What it does **not** have
 * is a reader for the *reconcile* half, and that is a ruling rather than an
 * omission — see *"the inference gets no bell"* below.
 *
 * ## Who gets told, and who does not
 *
 * ⛔ **UNTIL 2026-08-21 NOBODY WAS TOLD ANYTHING, AND THE SHAPE OF THAT IS
 * WORTH MORE THAN THE FIX** (7020, 7021). This command runs at 03:05 from
 * `routes/console.php`. Laravel's scheduler discards command output, so
 * `$this->warn(…)` below — *"Zernio still holds read and write access to those
 * former customers' Google listings, and we are still billed for the
 * accounts"* — has been written to nothing, every night, since 4880 shipped.
 * The same fact reached `Admin\GbpGrantRevocations`, which is a screen somebody
 * has to think to open. **A sentence nobody reads and a screen nobody opens is
 * the state 4888(a) described and the screen only half closed**: the screen
 * made the fact findable, and nothing made anybody look.
 *
 * ✅ **SO THE OUTSTANDING COUNT NOW RINGS THE BELL** —
 * {@see OperatorAlertKind::GbpGrantOutstanding}, raised through
 * {@see OperatorAlerts}, which is email, SMS and a `critical` log line. It rings
 * from the **sweep** and never from {@see TenantDeletion::execute()}'s immediate
 * call: a single failed revocation during an erasure is retried within the hour
 * by nothing and within the night by this, and paging somebody at the moment of
 * a transient vendor error is 511's *"tuned until nobody reads it"* with a
 * deletion request attached. **The cost of that choice is up to a day of
 * latency**, which is the right unit for an item billed per day.
 *
 * ⛔ **AND THE INFERENCE GETS NO BELL, WHICH IS THE DESIGN DECISION IN THIS
 * SLICE RATHER THAN A GAP IN IT** (7022). `--reconcile`'s population is exactly
 * as real a bill as the one above and it is deliberately silent, for a reason
 * that survives every argument about cost: **there is no action in this
 * application that clears one.** 4884 and 4888(b) forbid revoking on an
 * inference; the screen therefore offers that list no button; and an operator
 * who does the correct thing — confirms the business is gone and disconnects
 * the account in Zernio's own console — leaves the binding row here untouched,
 * so the probe finds it again tomorrow and the bell rings again for ever.
 * **An alert on a state nobody can clear is a bell somebody mutes**, and it
 * would be muted together with the one above, which shares its screen and its
 * subject. So the inference stays where 4884 put it: a button on a screen, one
 * click from the alert that does ring.
 *
 * ⚠️ **THE `skipped` COUNT GETS NO BELL EITHER, AND FOR 5074's REASON RATHER
 * THAN FOR SYMMETRY.** Those rows name a business that is **still a customer**.
 * Telling an operator at 3am that a former customer's grant is outstanding
 * about a live account is how somebody disconnects a paying tenant's Google
 * listing by hand, with no way back but asking the owner to consent again at
 * Zernio.
 *
 * ⚠️ **A CLEAN RUN DOES NOT CLEAR A MONTH** (6919). Silence here means no
 * revocation was owed and refused *tonight*. The invoice is an integral over
 * `zernio_account_days` and an account connected on the 3rd and revoked on the
 * 9th is on it, on a night this command said nothing about.
 */
#[Signature('gbp:revoke-owed-grants {--reconcile : Also report bindings whose business no longer exists}')]
#[Description('Revoke the Google Business grants left behind by deleted tenants')]
final class RevokeOwedGbpGrants extends Command
{
    public function handle(GbpConnections $connections, OperatorAlerts $alerts): int
    {
        $owed = $connections->owedGrantCount();

        if ($owed === 0) {
            $this->info('No Google Business grants are owed a revocation.');
        } else {
            $result = $connections->revokeOwedGrants();

            $this->info("Revoked {$result['revoked']} Google Business grant(s) left by deleted accounts.");

            if ($result['skipped'] > 0) {
                // ⛔ **A DIFFERENT SENTENCE FROM THE ONE BELOW, ON PURPOSE**
                // (5074). These rows were never sent to Zernio: they carry an
                // obligation whose business is still here, which after 5073 can
                // only be a stamp written before that fix shipped. Telling an
                // operator that a former customer's grant is outstanding would
                // send them to disconnect a live account by hand.
                $this->warn(
                    "{$result['skipped']} owed grant(s) name a business that still exists and were "
                    .'left alone. Nothing was sent to Zernio: revoking one would disconnect a live '
                    .'customer\'s Google listing. They are on the Ops grant-revocation screen, marked.'
                );
            }

            if ($result['outstanding'] > 0) {
                // ⚠️ Warned rather than failed, on `tenants:execute-deletions`'
                // own rule: the scheduler discards output, so a non-zero exit is
                // the only signal — and a vendor being down overnight is not an
                // incident this command can resolve by reddening. It tries again
                // tomorrow, and the row stays.
                $this->warn(
                    "{$result['outstanding']} grant(s) could not be revoked and are still recorded as owed. "
                    .'Zernio still holds read and write access to those former customers\' Google listings, '
                    .'and we are still billed for the accounts.'
                );

                $this->ring($alerts, $result);
            }
        }

        if ($this->option('reconcile') === true) {
            $this->reportUnrecorded($connections);
        }

        return self::SUCCESS;
    }

    /**
     * Ring the bell about the grants the vendor would not end tonight.
     *
     * ⛔ **THE SUMMARY CARRIES COUNTS AND NOTHING ELSE — NO BUSINESS REFERENCE,
     * NO LOCATION, AND ABOVE ALL NO ACCOUNT REFERENCE.** This sentence is
     * delivered by SMS, so it is the one line on this path that leaves the
     * platform in a form somebody can forward. The account reference is the
     * value that decides whose Google listing a `DELETE` reaches
     * ({@see GbpConnections}' own docblock, and 4884's rule that the orphan
     * probe hands back business references for exactly this reason), and a
     * business reference on a text message is an invitation to paste it into a
     * vendor console as one. **The evidence is in
     * `gbp_grant_revocation_attempts` and on the screen, where it is behind the
     * admin gate.**
     *
     * ⚠️ **THE COUNTS ARE THE WHOLE DIAGNOSIS AN OPERATOR NEEDS BEFORE OPENING
     * ANYTHING**: how many are stuck, whether tonight's sweep achieved anything
     * at all, and whether the number is one former tenant or the whole backlog.
     * `skipped` rides along in the context and deliberately not in the sentence
     * — see this class's docblock.
     *
     * ⚠️ **R25: A BELL, NEVER A BRAKE.** {@see OperatorAlerts::raise()} returns
     * rather than throws and contains its own failures, so a broken alert path
     * cannot stop the sweep, and the command still exits 0 on an unrevoked
     * grant (4887).
     *
     * @param  array{revoked: int, outstanding: int, skipped: int}  $result
     */
    private function ring(OperatorAlerts $alerts, array $result): void
    {
        $alerts->raise(
            OperatorAlertKind::GbpGrantOutstanding,
            // The provider, not a business: every business named by this alert
            // has already been destroyed. See the enum case's own docblock.
            GbpProvider::Zernio->value,
            "{$result['outstanding']} Google Business grant(s) belonging to deleted customers could not be "
            .'revoked tonight. Until they are, Zernio can read and write those former customers\' Google '
            .'listings and we are billed for the accounts every day.',
            [
                'provider' => GbpProvider::Zernio->value,
                'outstanding' => $result['outstanding'],
                'revoked' => $result['revoked'],
                'skipped' => $result['skipped'],
            ],
        );
    }

    /**
     * The bindings nothing recorded — reported, never acted on.
     */
    private function reportUnrecorded(GbpConnections $connections): void
    {
        $unrecorded = $connections->bindingsWithNoSurvivingBusiness();

        if ($unrecorded->isEmpty()) {
            $this->info('Every remaining binding belongs to a business that still exists.');

            return;
        }

        // The reference, never a name or an account id: this line reaches a log
        // file, and `audit_log.actor`'s convention is that a label is safe where
        // a third party's identifier is not. The service hands back refs for
        // exactly that reason.
        $refs = $unrecorded->unique()->sort()->implode(', ');

        // Two lines rather than one long one: the console wraps at the terminal
        // width, and the references are the part an operator has to copy.
        $this->warn(
            "{$unrecorded->count()} binding(s) name a business that no longer exists and were never "
            .'recorded as owed. Nothing was revoked: these predate the recording path and are '
            .'inferred rather than known. Confirm each belonged to a deleted customer before '
            .'revoking it by hand.'
        );

        // ⚠️ **THIS SAID "Accounts:" UNTIL 5104 AND NAMED THE WRONG THING.**
        // These are **business** references — `bindingsWithNoSurvivingBusiness()`
        // returns business refs by design, precisely so that its caller never
        // holds a Zernio account id. Nothing leaked; what the label invited was
        // an operator pasting one of them into a vendor console as an account
        // id, which is the one place a wrong identifier disconnects somebody
        // else's Google listing. Pre-existing, not this wave's.
        $this->warn("Businesses: {$refs}");
    }
}
