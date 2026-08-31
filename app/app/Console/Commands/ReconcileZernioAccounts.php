<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\GbpProvider;
use App\Enums\OperatorAlertKind;
use App\Exceptions\GbpRequestFailed;
use App\Services\Config\DefaultsRegistry;
use App\Services\Gbp\ZernioReconciliation;
use App\Services\Gbp\ZernioReconciliationReport;
use App\Services\Ops\OperatorAlerts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Ask Zernio what is connected, diff it against what we hold, and ring if the
 * gap costs money — 6779(d), 6917, 7393, 7394, the owner's figure at 9200.
 *
 * ## What was missing, and it was not the detection
 *
 * {@see ZernioReconciliation} has computed all of this since 6779(d) closed.
 * Its only caller in `app/` was a **button** on `Admin\GbpGrantRevocations`, so
 * the answer existed exactly as often as somebody thought to open that screen —
 * *"a sentence nobody reads and a screen nobody opens"*, which is the state
 * 4888(a) described and `RevokeOwedGbpGrants` already had to fix once for the
 * grant half of the same problem. 7393 declined to ship the sweep without a
 * bell for the same reason: **a command nothing schedules is 2578/2590/3483's
 * shape**, and a scheduled one whose output the scheduler discards is 7241's.
 *
 * ## ⛔ It reports, and it may never act
 *
 * ⛔ **NOTHING HERE REVOKES, DISCONNECTS, BINDS OR REPAIRS ANYTHING** (4884,
 * 4888(b), 6767). An orphan is an inference from an absence and three states
 * arrive identically (6766): an abandoned connect flow, an account connected in
 * Zernio's own console, and a redirect that has not landed yet. **Detect, count,
 * surface. A human reads it and acts, in a console this application cannot
 * reach.** That is why the bell is {@see OperatorAlertKind::GbpOrphanedAccounts}
 * at `Attention` and why this command has no `--fix` and never will.
 *
 * ## Why a bell here where `--reconcile` gets none
 *
 * ⚠️ **7022 REFUSED A BELL ON `gbp:revoke-owed-grants --reconcile` AND THE
 * REFUSAL DOES NOT REACH THIS ONE.** There, *nothing in this application clears
 * one*: an operator who confirms the business is gone and disconnects it at
 * Zernio leaves the binding row here untouched, so the probe finds it again
 * tomorrow and the bell rings for ever and is muted. **Here the correct action
 * makes it stop.** Disconnecting an orphan in Zernio's console removes it from
 * the vendor's own list, and the vendor's list is the side of the diff this
 * reads.
 *
 * ## ⛔ Three outcomes, because "the vendor could not be asked" is not "clean"
 *
 * {@see ZernioReconciliation::run()} is `@throws GbpRequestFailed` and its
 * docblock is emphatic: *"A VENDOR FAILURE PROPAGATES AND IS NEVER AN EMPTY
 * REPORT"* — 4720's permanently-zero meter reached through a `catch`. This
 * command therefore catches the exception **to say which failure it was**, and
 * never to reach a conclusion:
 *
 *   client refused   {@see GbpRequestFailed::$clientRefused} (9145) is true only
 *                    for {@see GbpRequestFailed::disabled()} and
 *                    {@see GbpRequestFailed::unconfigured()} — the integration
 *                    is switched off, or no platform credential is set. **There
 *                    is nothing connected to reconcile and nothing has gone
 *                    wrong**, so this exits 0 with a line saying so. ⛔ A bell
 *                    here would ring on every install that has not connected
 *                    Google, every night, for ever — 511 with a handset
 *                    attached, and it would be muted together with the real one.
 *
 *   vendor failed    Zernio was asked and could not answer. **Exit non-zero and
 *                    state that nothing was concluded.** ⚠️ It rings no bell,
 *                    and that is a stated limit rather than an oversight: the
 *                    reconciliation is a nightly read of a monthly quantity, a
 *                    vendor being down overnight costs nothing that tomorrow's
 *                    run does not recover, and paging somebody at 3am about
 *                    another company's outage is the noise that gets the real
 *                    bell silenced. ⛔ **WHAT IT COSTS IS WRITTEN DOWN AT 9235
 *                    AND IS NOT COVERED BY ANOTHER BELL**:
 *                    {@see OperatorAlertKind::VendorErrorRate} reads
 *                    `PlatformHealthSignal::VendorCall`, and the only writer of
 *                    that signal is the AI router — no Google Business call is
 *                    counted there at all — so a Zernio outage lasting a month
 *                    makes this sweep silent and nothing announces the silence.
 *
 *   answered         The diff is printed, and the bell rings if the gap reaches
 *                    the threshold.
 *
 * ## ⚠️ A clean run does not clear a month
 *
 * The invoice is an integral over account-days and this is a count at an
 * instant, so an account connected on the 3rd and disconnected on the 9th is on
 * the bill and on neither side of tonight's report
 * ({@see ZernioReconciliationReport}'s own docblock). Silence here means *no
 * unaccounted-for account is connected right now*.
 */
#[Signature('zernio:reconcile')]
#[Description('Diff Zernio\'s connected accounts against our bindings and ring if the gap costs money')]
final class ReconcileZernioAccounts extends Command
{
    /**
     * `platform_settings` key, namespaced by area like every key in the group.
     *
     * ⛔ **A BELL, NOT A BRAKE — AND DELIBERATELY NOT `ZernioSpend::CEILING_KEY`,
     * WHICH IS NAMED IN PROSE RATHER THAN THROUGH A `{@see}` TAG ON PURPOSE**:
     * Pint's `fully_qualified_strict_types` turns one into a real `use`
     * statement, and an import here would be a Google Business class named by a
     * console command for no reason but a cross-reference. That one measures our
     * own bindings and refuses a connection; this measures the vendor's list
     * against ours and refuses nothing. Feeding either into the other's reader
     * would make a ceiling that asks a third party, which
     * {@see ZernioReconciliation}'s docblock refuses in bold.
     */
    public const string ALERT_KEY = 'gbp.zernio_orphan_alert_cents';

    /**
     * The monthly gap, in cents, at which the bell rings.
     *
     * ⛔ **PUBLIC AND STATIC BECAUSE `Admin\OperatorAlertBoard` HAS TO READ IT
     * OFF THE CLASS THAT APPLIES IT** — 7661, where a clamp copied into that
     * very file went stale in one merge, and 7780/7901, where two integrators in
     * two waves paid for a minted kind whose arming this screen could not
     * describe. `WatchPixelCanary::haltThresholdBp()` is the shape.
     *
     * ⚠️ **`max(0, …)` RATHER THAN A REFUSAL AT THE WRITE DOOR.** A negative
     * figure is nonsense rather than a dangerous instruction — it can only make
     * the bell ring on a gap of zero — and R25 makes a read that threw a bell
     * that is a brake. There is also **no honest upper bound to state**: the
     * platform's bill has none, so this key is not on `RegistryTest`'s bounded
     * list and could not join it.
     */
    public static function alertThresholdCents(DefaultsRegistry $registry): int
    {
        return max(0, $registry->int(self::ALERT_KEY));
    }

    public function handle(ZernioReconciliation $reconciliation, OperatorAlerts $alerts, DefaultsRegistry $registry): int
    {
        try {
            $report = $reconciliation->run();
        } catch (GbpRequestFailed $e) {
            return $this->reportUnaskable($e);
        }

        $this->describe($report);

        $threshold = self::alertThresholdCents($registry);

        if ($threshold <= 0) {
            // ⚠️ **ANNOUNCED ON EVERY RUN, ON `storage:prune`'s RULE** (4942):
            // what a fail-closed key buys is *a refusal that names the gap*, and
            // it is only worth anything where somebody can act on it. A
            // switched-off bell that says nothing is indistinguishable from a
            // platform with no orphans.
            // ⚠️ **TWO LINES RATHER THAN ONE LONG ONE**, on
            // `RevokeOwedGbpGrants`' rule: the console wraps at the terminal
            // width and the second sentence is the one that stops a switched-off
            // bell reading as a clean platform, so it must not be the half that
            // wrapped. ⚠️ **It is also what makes the claim testable** —
            // `expectsOutputToContain()` is satisfied per written line, so two
            // phrases from one call can only ever assert the first.
            $this->warn('No alert threshold is set ('.self::ALERT_KEY.' is 0), so nothing here can ring.');
            $this->warn(
                'Unaccounted-for accounts are still counted above and are still listed on the Ops '
                .'grant-revocation screen.'
            );

            return self::SUCCESS;
        }

        if ($report->orphanMonthCents() < $threshold) {
            return self::SUCCESS;
        }

        $this->ring($alerts, $report, $threshold);

        return self::SUCCESS;
    }

    /**
     * The two ways the vendor cannot be asked, which are not one way.
     *
     * ⚠️ **FLAG FIRST, THE SAME ORDER `ZernioGbpClient::assertUsable()` USES.**
     * A switched-off integration with no key pasted is not a credential fault,
     * and reporting it as one sends an operator to paste a key into a vendor
     * nobody has turned on.
     */
    private function reportUnaskable(GbpRequestFailed $e): int
    {
        if ($e->clientRefused) {
            $this->info(
                'Zernio is not in use on this install, so there are no connected accounts to '
                .'reconcile ('.$e->reason.').'
            );

            return self::SUCCESS;
        }

        // ⛔ **NOT `SUCCESS`, AND NOT A CLEAN REPORT EITHER.** The scheduler
        // discards output, so this line reaches a person only when they run the
        // command by hand — which is exactly when an exit code is the thing they
        // can script against (7390). What matters more is the sentence: this run
        // concluded **nothing**, and *"no unaccounted-for accounts"* is the one
        // conclusion a broken reconciliation must never reach on its own.
        //
        // ⚠️ `reason` is Zernio's machine-readable code and never its human
        // message or its body — `GbpRequestFailed` refuses to carry either,
        // because Google's own error bodies echo request parameters and can
        // quote review content.
        $this->error(
            'Zernio could not be asked which accounts are connected ('.$e->reason.'), so nothing '
            .'was reconciled tonight. It runs again tomorrow.'
        );

        // ⚠️ **ITS OWN LINE, BECAUSE IT IS THE CLAIM AND NOT THE DETAIL.** The
        // sentence above says what failed; this one says what was NOT concluded,
        // and an operator skimming a cron log is the reader it exists for.
        $this->error(
            'This is not a clean report: unaccounted-for accounts may exist and were not counted.'
        );

        return self::FAILURE;
    }

    /**
     * The diff, in the order an operator reads it.
     *
     * ⚠️ **BUSINESS REFERENCES AND COUNTS ONLY.** The report carries a
     * `profileRef` per orphan and this deliberately does not print it: 5104
     * already had to correct a console label that invited an operator to paste
     * one into a vendor console as an account id, and 4884's whole rule is that
     * the value deciding whose Google listing a call reaches stays inside
     * `GbpConnections`. The evidence is on `Admin\GbpGrantRevocations`, behind
     * the admin gate.
     */
    private function describe(ZernioReconciliationReport $report): void
    {
        $this->info(sprintf(
            'Zernio lists %d connected account(s); %d binding(s) here account for them.',
            $report->billedAccounts,
            $report->boundAccounts,
        ));

        if ($report->unreadable > 0) {
            // ⚠️ **COUNTED IN `billedAccounts` AND ABSENT FROM `orphans`**, so
            // the two totals visibly fail to add up rather than silently doing
            // so — and it is why `orphanCount()` can read zero while
            // `orphanMonthCents()` does not.
            $this->warn(sprintf(
                '%d entry(ies) in the vendor\'s list carry no readable account id. They are billed '
                .'and cannot be matched against anything of ours.',
                $report->unreadable,
            ));
        }

        if ($report->businessesBoundButNotConnected !== []) {
            // The other direction, and it is not harmless: `zernio:meter` writes
            // an account-day for every binding, so a binding the vendor no
            // longer lists makes our accrual overstate the bill and makes
            // `ZernioSpend::allowsNewAccount()` refuse connections the platform
            // could afford.
            $this->warn(sprintf(
                '%d binding(s) here name an account Zernio no longer lists, so our accrual '
                .'overstates the bill. Businesses: %s',
                count($report->businessesBoundButNotConnected),
                implode(', ', $report->businessesBoundButNotConnected),
            ));
        }

        $this->line(sprintf(
            '%d account(s) connected at Zernio are not bound to any location here. At the current '
            .'account count that is $%s a month.',
            $report->orphanCount(),
            number_format($report->orphanMonthCents() / 100, 2),
        ));
    }

    /**
     * Ring the bell about accounts nothing here is using.
     *
     * ⛔ **THE SUMMARY CARRIES COUNTS AND NO IDENTIFIER OF ANYBODY'S, BECAUSE
     * THIS ONE LEAVES THE PLATFORM BY SMS** (7025, 7394(d)).
     * `OperatorAlerts::text()` sends `headline(): summary` to the operator's
     * handset, so this is the one line on the path a person can forward. ⛔ **The
     * report hands this method the forbidden value directly**: every
     * `ZernioOrphanedAccount` carries a `profileRef`, and a profile reference is
     * the handle to `GET /v1/accounts?profileId=…` — the query that reads a
     * tenant's accounts — one level up from the account id that decides whose
     * listing a `DELETE` reaches (4884). **Neither the summary nor the context
     * may carry one, and neither may carry a business id**: a business reference
     * in a text message is an invitation to paste it into a vendor console as an
     * account id, which 5104 has already caught once.
     *
     * ⚠️ **THE SENTENCE CARRIES TWO COUNTS AND THE CONTEXT CARRIES FIVE, AND
     * THE SPLIT IS THE 300-CHARACTER COLUMN RATHER THAN A JUDGEMENT.** *"1 of 2"*
     * is a connect flow somebody abandoned and *"40 of 41"* is a key or a profile
     * that has come apart, which is the whole of what a handset at 3am can carry.
     * The threshold that was crossed, the binding count and the unreadable count
     * ride in the context, where nothing truncates them, and on the alert board.
     * ⛔ **The context is NOT a place to put what the sentence could not fit if
     * it names anybody** — it is the same payload and the same rule (7025).
     *
     * ⚠️ **R25: A BELL, NEVER A BRAKE.** {@see OperatorAlerts::raise()} contains
     * its own failures and returns rather than throwing, so a broken alert path
     * cannot stop the sweep — and the command exits 0 on a ringing bell, because
     * the run succeeded and the finding is the point.
     */
    private function ring(OperatorAlerts $alerts, ZernioReconciliationReport $report, int $threshold): void
    {
        $alerts->raise(
            OperatorAlertKind::GbpOrphanedAccounts,
            // The provider, not a business and never an account: half of these
            // accounts are unattributable by construction, and a per-account
            // subject would put the value 4884 keeps inside `GbpConnections`
            // onto a platform-scoped table and a staff screen. See the enum
            // case's own docblock, and `GbpGrantOutstanding`'s precedent (7024).
            GbpProvider::Zernio->value,
            self::summarise($report),
            [
                'provider' => GbpProvider::Zernio->value,
                'orphans' => $report->orphanCount(),
                'orphan_month_cents' => $report->orphanMonthCents(),
                'threshold_cents' => $threshold,
                'billed_accounts' => $report->billedAccounts,
                'bound_accounts' => $report->boundAccounts,
                'unreadable' => $report->unreadable,
            ],
        );
    }

    /**
     * One sentence, inside the column that carries it.
     *
     * ⛔ **`operator_alerts.summary` IS `string(300)` AND
     * {@see OperatorAlerts::fire()} CLIPS WITH `mb_substr()` RATHER THAN
     * REFUSING** — correctly, because *"a summary two characters over the column
     * is not a reason to swallow an outage alert"*. ⚠️ **But the clip takes the
     * TAIL, and the tail is where a writer puts the action.** This slice's first
     * draft ran to 371 characters and lost exactly the two clauses the bell
     * exists for: *we have no record of whose*, and *only Zernio's own console
     * can end one*. It was caught by an assertion rather than by reading, and
     * nothing anywhere reports a clipped summary — see 9235.
     *
     * ⚠️ **SO THE ORDER IS THE MITIGATION**: what an operator must read survives
     * a clip only by being early, and the figures that would grow the sentence —
     * the counts, and the threshold this was measured against — are in the
     * context, where nothing truncates them, and on the alert board.
     *
     * ⛔ **PUBLIC AND STATIC SO THAT THE LENGTH CAN BE DRIVEN AT VALUES NO
     * FIXTURE WILL EVER PRODUCE.** A clip is silent, so a test over a realistic
     * report proves nothing about the sentence at four-digit counts and
     * four-digit dollars — which is the shape the day this platform actually has
     * a problem. `ZernioOrphanBellTest` composes one directly and asserts it
     * still fits.
     *
     * ⛔ **AND NOTHING IN IT MAY NAME ANYBODY** (7025, 7394(d)). It leaves the
     * platform by SMS, so it carries counts, cents and the provider, and never a
     * profile reference, an account reference or a business reference — every
     * orphan on the report carries the first of those, and half of them carry
     * the third.
     */
    public static function summarise(ZernioReconciliationReport $report): string
    {
        $sentence = sprintf(
            '%d of the %d Google accounts Zernio bills us for are bound to nothing here, about '
            .'$%s a month. Each is a live read and write grant on a real business\'s Google listing '
            .'and we have no record of whose. Only Zernio\'s own console can end one.',
            $report->orphanCount(),
            $report->billedAccounts,
            number_format($report->orphanMonthCents() / 100, 2),
        );

        if ($report->unreadable > 0) {
            // ⚠️ **CONDITIONAL, BECAUSE WITHOUT IT THE SENTENCE READS AS
            // NONSENSE ON THE ONE SHAPE THAT NEEDS IT MOST.** An unreadable
            // entry counts into `billedAccounts` and never into `orphans`, so
            // *"0 of the 4 accounts are bound to nothing here, about $12.00 a
            // month"* is what an operator would otherwise be handed.
            $sentence .= sprintf(' %d carry no readable account id.', $report->unreadable);
        }

        return $sentence;
    }
}
