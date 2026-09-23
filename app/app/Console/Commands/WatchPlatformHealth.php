<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ops\OperatorAlerts;
use App\Services\Ops\PlatformHealth;
use App\Services\Ops\PlatformHealthChecks;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Read the platform's own counters and ring the bell if any of them is bad
 * (T176 §3, P23).
 *
 * ⚠️ **THE HEARTBEAT CHECK IS DELIBERATELY NOT HERE.** It runs in the web
 * process, because a sweep that only runs while the scheduler runs cannot report
 * that the scheduler stopped — {@see PlatformHealthChecks} carries the full
 * argument. What is here is the half that genuinely belongs on a clock: counters
 * that other code wrote, read on an interval.
 *
 * ⛔ **IT STOPS NOTHING** (R25). No branch of this command halts sending,
 * refuses a tenant, or degrades anything. Compare `messaging:watch-platform-
 * complaint-rate`, which throws a switch: that one seeds its thresholds at zero
 * because a machine may not stop the platform on a figure nobody chose. This one
 * seeds its thresholds *on*, because the worst thing a pager can do is nothing.
 *
 * ⛔ **T176's "TRANSCRIPTION FAILURES" IS NOT CHECKED HERE, AND THAT IS A
 * REFUSAL RATHER THAN AN OVERSIGHT.**
 *
 * ⛔ **TWO CLAUSES OF THIS PARAGRAPH WERE FALSE AND ARE CORRECTED — 2026-08-21.**
 * It read *"There is no transcription path in `app/` at all … and
 * `VoicemailTranscribed` is an event nothing dispatches."* **Both are wrong.**
 * `app/Jobs/Voice/TranscribeVoicemailJob.php` is the path, and its line 182 is
 * the dispatch. ⚠️ **The error pointed a reader the wrong way down the chain**:
 * believing nothing *produces* the event sends you looking for a dispatcher that
 * already exists, when the gap is that nothing *consumes* it —
 * `app/Listeners/Voice` holds a listener for `VoicemailRecorded` and one for
 * `CallMissed`, and none for this.
 *
 * ⚠️ **THE REFUSAL ITSELF STANDS, ON A NARROWER FOOTING.** The vendor is still
 * open (`CLAUDE.md` §Vendors, "decide at Stage 6b") and `NullTranscriber` is the
 * bound implementation, so no transcript is ever produced on any deployment. A
 * threshold on a
 * counter nothing writes is `sending_health_windows` again: a check that reads
 * zero for ever, passes every test, and reports a healthy platform because the
 * feature does not exist (272, 2496–2499, and 256's vacuous lint).
 *
 * ⚠️ **WHAT IS BUILT INSTEAD IS THE SHAPE THAT MAKES IT TWO LINES LATER.**
 * `PlatformHealthSignal::VendorCall` is keyed by source and the sweep
 * **discovers** its sources from the rows rather than from a list — so whoever
 * builds transcription calls `PlatformHealth::recordFailure(VendorCall,
 * 'deepgram')` beside their existing `VendorLog::failure()` and is watched from
 * the first call, with no change to this command and no new threshold.
 *
 * ⛔ **"IT SAYS WHEN IT IS DEAF, ON EVERY RUN" WAS TRUE OF THE CODE AND FALSE
 * OF PRODUCTION FROM THE DAY IT WAS WRITTEN — CORRECTED 2026-08-21.** The
 * paragraph continued *"an alert reaches a log file and nothing else — and a
 * scheduled task that prints 'nothing to do' is how a switched-off safety
 * control stays switched off for a year"*, and the lesson is right. **The
 * mechanism was not.** {@see self::warnIfNobodyIsListening()} writes to the
 * console, and this command's schedule entry is `->runInBackground()`, which
 * makes the framework append `> /dev/null` to the process it launches — before
 * the cron line in `.claude/skills/deploying/SKILL.md` redirects the whole
 * `schedule:run` to `/dev/null` as well. **Announced into `/dev/null` twice**,
 * while a test asserting the wording stayed green.
 *
 * ⚠️ **THE WARNING STAYS, ON HONEST TERMS.** It is the right output for the two
 * ways a person runs this deliberately — `php artisan ops:watch-platform-health`
 * and `schedule:test --name=…`, which the deploy skill already calls the only
 * direct proof the path works. What it is not, and never was, is an
 * announcement that reaches an unattended install.
 *
 * ⚠️ **"COUNTERS THAT OTHER CODE WROTE" IS ONE WORD SHORT SINCE 2026-08-23**
 * (8270–8289). `sweepCounters()` also reads one **state** — whether this install
 * can still read the suppression hashes it has stored — which has no counter, no
 * threshold and nothing to tune, and which refuses **every send on the platform**
 * while it is true. It belongs on this clock for this clock's reason: it is a
 * fact this platform already knows about itself and nothing was looking at it.
 * ⛔ **It is emphatically still a bell and not a brake** — the refusal it reports
 * is `ConsentService`'s and was there before this command noticed.
 *
 * ⚠️ **WHERE THE DEAF STATE IS ACTUALLY ANNOUNCED IS THREE PLACES, NONE OF THEM
 * THIS ONE.** The `critical` log line for every alert names the push channels it
 * will reach or `none` ({@see OperatorAlerts::pushChannels()}); `ops:alert-channels`
 * asks the question deliberately and counts the alerts that reached nobody; and
 * `composer deploy` runs that command as its last step, which is the one moment
 * an operator is reliably reading this application's output.
 */
#[AsCommand(name: 'ops:watch-platform-health')]
final class WatchPlatformHealth extends Command
{
    /**
     * How long a counter row is kept.
     *
     * ⚠️ **NOT A REGISTRY KEY.** These rows are a few dozen a day and nothing
     * reads one older than the alert window; a configurable retention here would
     * be a setting whose only possible effect is to make the table bigger. Thirty
     * days is long enough for an incident review to look at last week.
     *
     * ⛔ **IT IS NOT `$alerts->retentionDays()` AND THE TWO MUST NOT BE
     * HARMONISED** (7520–7539). This is the **measurement**; that is the
     * **bell that was rung about it**, and `operator_alerts.context` is jsonb
     * precisely so an incident review can read the figures after these buckets
     * have gone — the creating migration's own *"by the time anybody looks, the
     * window has rolled and the sample is gone"*. Making the alert expire with
     * its counters deletes the surviving copy of the evidence, which is the one
     * thing that table was designed to hold.
     */
    public const int KEEP_DAYS = 30;

    protected $description = 'Alert the operator when the platform\'s own counters cross their thresholds';

    public function handle(
        PlatformHealthChecks $checks,
        PlatformHealth $health,
        OperatorAlerts $alerts,
    ): int {
        $this->warnIfNobodyIsListening($alerts);

        $raised = $checks->sweepCounters();

        $this->line($raised === 0
            ? 'Platform health: nothing over threshold.'
            : "Platform health: {$raised} alert(s) raised.");

        // Cheap and matches nothing on an ordinary day. Run last so a prune that
        // fails cannot cost an alert.
        $health->prune(self::KEEP_DAYS);

        // ⛔ **AND THE BELLS THEMSELVES, LAST OF ALL** (7520–7539). Same
        // reasoning one table over, and the same reason for the position: a
        // prune that fails must cost nothing that was already earned. ⚠️ **It
        // needs no schedule entry of its own** — a range delete on an indexed
        // column that matches nothing on an ordinary day has no business
        // holding its own overlap lock, and the sweep that raises these rows is
        // the natural owner of the horizon they expire on.
        //
        // ⚠️ **THE CLAMP IS INSIDE `OperatorAlerts::prune()` AND MUST STAY
        // THERE.** The row is the de-duplication as well as the record, so a
        // horizon applied here — without the quiet window in front of it —
        // re-arms every alert that was already sent. This command deliberately
        // passes a number and knows nothing about the dedupe.
        $alerts->prune($alerts->retentionDays());

        return self::SUCCESS;
    }

    /**
     * ⚠️ **BOTH CHANNELS UNSET IS THE STATE A FRESH INSTALL IS IN**, and it is
     * the state in which every alert this feature raises reaches a row and a log
     * line and no push channel at all. It is a warning rather than a failure
     * because R25 is emphatic that nothing here may block, and because a command
     * that exits non-zero on a fresh install teaches an operator to ignore its
     * exit code.
     *
     * ⚠️ **IT ASKS {@see OperatorAlerts::pushChannels()} RATHER THAN RE-READING
     * THE TWO ROWS.** It used to read and `trim()` them itself, which is a second
     * copy of the rule *blank is the off switch* sitting where nothing compares
     * the two — so a change to what counts as configured would have moved the
     * bell and left the warning describing the old one.
     */
    private function warnIfNobodyIsListening(OperatorAlerts $alerts): void
    {
        if ($alerts->pushChannels() !== []) {
            return;
        }

        $this->warn(
            'NO OPERATOR ALERT ADDRESS AND NO ALERT NUMBER ARE SET: '
            .OperatorAlerts::EMAIL_KEY.' and '.OperatorAlerts::SMS_KEY.' are both blank, '
            .'so every alert this command raises is recorded and logged and pages nobody. '
            .'Set at least one in Ops settings, and run `php artisan ops:alert-channels` to check.'
        );
    }
}
