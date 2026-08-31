<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OperatorAlert;
use App\Services\Ops\OperatorAlerts;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

/**
 * Who can this platform page, and who did the last alerts actually reach?
 *
 * ⚠️ **THE GAP THIS CLOSES IS ANNOUNCEMENT, NOT CAPABILITY.** `ops.alert_email`
 * and `ops.alert_sms` have been settable on `admin/settings` since the day they
 * shipped, and both **seed blank on purpose** — an address cannot be invented,
 * and a plausible-looking `ops@` would send every alert into a void that looks
 * configured. What was missing is that nothing said so anywhere an operator
 * looks: no skill file mentioned either key, and the one runtime announcement
 * was a `$this->warn()` from a `runInBackground()` schedule entry, which the
 * framework redirects to `/dev/null` before cron redirects it again.
 *
 * ⛔ **IT STOPS NOTHING AND IT ALWAYS EXITS ZERO — R25, AND `composer deploy`
 * IS WHY THE SECOND HALF IS NOT TASTE.** Composer halts on the first non-zero
 * script, so a check that failed on a fresh install would strand a deployment
 * after `queue:restart` had already run, over a registry row. A bell may not be
 * a brake, and a deploy step that reports a bell is still not a brake. The same
 * reasoning is already written on
 * {@see WatchPlatformHealth::warnIfNobodyIsListening()}: a command that exits
 * non-zero on a fresh install teaches its operator to ignore the exit code.
 *
 * ## What it can prove, and what it cannot
 *
 * ⚠️ **IT READS THE RUNNING INSTALL'S REGISTRY, WHICH IS THE ONLY THING THAT
 * CAN.** `CLAUDE.md`'s *a seed is not a deployment* is exactly this: the seeds
 * are a fact about a fresh checkout, an operator's edit is a fact about the box,
 * and no document in this repository may state which is in force. A command run
 * on the box can.
 *
 * ⛔ **AND IT COUNTS DELIVERY FAILURES, NEVER DELIBERATE SILENCE** (7820–7839).
 * `OperatorAlerts` withholds a push once an inbound request has spent that
 * kind's daily budget, which leaves `emailed_at` and `texted_at` both null —
 * indistinguishable, from these two columns alone, from a mailbox that bounces.
 * The rows carry `push_withheld_at` and the query below excludes them, so a
 * pager that is working exactly as designed under a flood does not read here as
 * a dead one. ⚠️ **The flood itself is not this command's to report** and
 * deliberately is not reported here: it is a `warning` per withheld push in the
 * log, and the alerts themselves are all on `admin/operator-alerts`.
 *
 * ⛔ **SET IS NOT REACHABLE.** A typo'd address, a mailbox that bounces, a
 * number that has changed hands and an SMS driver still on `log` all read as
 * *set* here. The second half of the output is the antidote and is the half
 * worth reading: **alerts that were raised and reached no push channel**, taken
 * from `emailed_at` and `texted_at` on the rows themselves rather than from the
 * configuration. A configured channel with a column full of nulls is the shape
 * a green settings screen cannot show.
 *
 * ⛔ **AND THAT ANTIDOTE WAS HALF INERT UNTIL 2026-08-25, IN EXACTLY THE
 * INCIDENT IT EXISTS FOR — 9371.** `emailed_at` was stamped immediately after
 * `PlatformMailer::send()`, which is a bare `DeliverPlatformMail::dispatch()`
 * and cannot fail by design (702) — so on any install with an address
 * configured the column was **never** null, and the query below could only ever
 * report an alert raised with no address at all, which the first half of this
 * output already says. ⛔ **During the 2026-08-20 mail outage every alert would
 * have read `emailed_at` set**, and this count would have printed zero while no
 * email was leaving the platform. ⚠️ **The asymmetry is what hid it**:
 * `texted_at` was honest throughout, because `PlatformTexter::alertOperator()`
 * is synchronous. ✅ **`OperatorAlerts::email()` calls `deliverNow()` now**, so
 * both columns mean the channel accepted the message and this count is a real
 * measurement of a broken push path rather than a query that cannot return one.
 * ⚠️ **What it still cannot see is unchanged and is the paragraph above**:
 * acceptance is not arrival.
 *
 * ⛔ **AND *"`texted_at` WAS HONEST THROUGHOUT"* WAS ITSELF THE SECOND HALF
 * OF THE SAME DEFECT — CORRECTED 2026-08-28 (11460).** Synchronous is not
 * delivering. `LogTexter::send()` returns a `SentText` for a message that
 * reaches nobody and `.env.example` ships `SMS_DRIVER=log`, so on the default
 * deployment `texted_at` was stamped from a fabricated acceptance and **this
 * count printed zero for the SMS channel for the same reason it printed zero
 * for mail** — the fault 9371 fixed, on the column 9371 named as the honest
 * one, in the paragraph that named it. ⚠️ **It is the reason the third
 * sentence of this docblock is not redundant**: *"an SMS driver still on `log`
 * … read as **set** here"* was written about the configuration half, and the
 * evidence half had the same hole. ✅ **`alertOperator()` now refuses a
 * transport that does not declare `App\Contracts\ReachesRecipients`**, so a
 * `log` install leaves `texted_at` null and lands **inside** this count.
 *
 * ⚠️ **AND THE OTHER HALF OF THAT ANSWER IS NOW `ops:alert-probe`, WHICH THIS
 * COMMAND POINTS AT ON THE ONE BRANCH WHERE IT IS THE ANSWER.** The undelivered
 * count catches a broken channel only once an alert has been raised, and on a
 * platform where nothing has gone wrong yet there are none — so until that
 * command existed, moving past *"reads the same either way"* meant borrowing a
 * real `OperatorAlertKind` and leaving a row **inside this very count**, for a
 * year. {@see ProbeOperatorAlertChannels} sends and records nothing.
 *
 * ⛔ **THAT SENTENCE SAID "A PERMANENT ROW IN A TABLE NOTHING PRUNES" UNTIL
 * 2026-08-22 — CORRECTED** (7520–7539). `OperatorAlerts::prune()` removes a row
 * after `OperatorAlerts::RETENTION_DAYS`. ⚠️ **The correction changes nothing
 * about this command**: `RETENTION_DAYS` is deliberately longer than
 * {@see WatchPlatformHealth::KEEP_DAYS}, which is the window counted below, so
 * a fabricated row is inside this count for its whole life either way. **A
 * horizon was never the answer to the defect the probe was built for.**
 *
 * ⛔ **THE TWO ARE NOT INTERCHANGEABLE AND THIS ONE STAYS THE DEPLOY STEP.**
 * This reads two rows and a table; that one sends. A deploy that paged the
 * operator on every deployment is 511 with a phone attached, and a test fails
 * the build if `ops:alert-probe` ever appears in `composer deploy`.
 *
 * ⚠️ **AND AN `sms` CHANNEL LISTED AS SET IS STILL NOT A TEXT ARRIVING.**
 * `PlatformTexter::alertOperator()` has to find a number to send *from* after it
 * reads the recipient, and on a platform whose every number is retired it
 * returns null. {@see OperatorAlerts::pushChannels()} says so too.
 */
#[AsCommand(name: 'ops:alert-channels')]
final class ShowOperatorAlertChannels extends Command
{
    /**
     * How many undelivered alerts are named before the count stands in for the
     * list. Five is a deploy-time output rather than an incident review;
     * `admin/operator-alerts` is the screen that shows every one.
     */
    private const int LISTED = 5;

    protected $description = 'Show which operator alert channels are configured, and which recent alerts reached nobody';

    public function handle(OperatorAlerts $alerts): int
    {
        try {
            $this->report($alerts);
        } catch (Throwable $e) {
            // R25's outermost net, in the shape a deploy step needs it: an
            // unreadable registry or an unreachable database must not be the
            // reason a deployment stops — and must not be reported as "nobody is
            // listening" either, because that is a different fact.
            $this->warn('Could not read the operator alert channels ('.$e::class.'). '
                .'Check '.OperatorAlerts::EMAIL_KEY.' and '.OperatorAlerts::SMS_KEY.' by hand.');
        }

        return self::SUCCESS;
    }

    private function report(OperatorAlerts $alerts): void
    {
        $channels = $alerts->pushChannels();

        $this->line('Operator alert channels — who this platform can page when its own machinery breaks.');

        // ⛔ **THE VALUES ARE DELIBERATELY NOT PRINTED.** They are an operator's
        // own address and mobile number, and this output is the tail of a deploy
        // that is routinely piped into a file. `set` is also the whole of what
        // this can honestly claim: a typo reads identically to a correct value,
        // and the undelivered count below is what catches that.
        $this->line('  '.OperatorAlerts::EMAIL_KEY.': '.(in_array('email', $channels, true) ? 'set' : 'NOT SET'));
        $this->line('  '.OperatorAlerts::SMS_KEY.': '.(in_array('sms', $channels, true) ? 'set' : 'NOT SET'));

        if ($channels === []) {
            $this->warn('NOBODY IS LISTENING. Every alert this platform raises is written to '
                .'operator_alerts and logged at level critical, and reaches no push channel at all. '
                .'Set at least one of the two rows above in Ops settings, group Operations.');
        }

        $this->reportUndelivered();
    }

    /**
     * The alerts that were raised and reached nobody.
     *
     * ⚠️ **THIS IS THE EVIDENCE HALF, AND IT ANSWERS A QUESTION THE SETTINGS
     * SCREEN CANNOT.** A configured address with a column of nulls beside it is a
     * mail path that is broken rather than absent, and the two need different
     * work; both look identical on a screen that shows the address.
     *
     * ⚠️ **AN EMPTY TABLE IS NOT A HEALTHY ONE AND IT SAYS SO** — a fresh install
     * reads identically to a platform that has never had an incident, which is
     * `ops:schedule-runtimes`' own most important line and 2496–2499's shape in a
     * console.
     */
    private function reportUndelivered(): void
    {
        $since = CarbonImmutable::now()->subDays(WatchPlatformHealth::KEEP_DAYS);

        $raised = OperatorAlert::query()->where('fired_at', '>=', $since)->count();

        if ($raised === 0) {
            $this->line('No alert has been raised in the last '.WatchPlatformHealth::KEEP_DAYS
                .' days — which reads the same whether nothing has gone wrong or nothing is watching.');

            // ⚠️ **THE POINTER GOES HERE AND NOWHERE ELSE, WHICH IS 511 RATHER
            // THAN BREVITY.** This is the one branch on which the output is
            // ambiguous and something can be done about it; a line printed on
            // every deploy regardless would be advice nobody reads by the third
            // deployment. It is deliberately absent from the deaf branch above,
            // where the answer is to set a row rather than to test one.
            $this->line('To find out whether a page would actually arrive, run: php artisan ops:alert-probe. '
                .'It sends one test message to the addresses above and writes no alert row, so it cannot '
                .'be confused with an incident.');

            return;
        }

        /** @var list<OperatorAlert> $undelivered */
        $undelivered = OperatorAlert::query()
            ->where('fired_at', '>=', $since)
            ->whereNull('emailed_at')
            ->whereNull('texted_at')
            // ⛔ **A WITHHELD PUSH IS NOT AN UNDELIVERED ONE, AND WITHOUT THIS
            // LINE THIS COMMAND WOULD REPORT A WORKING PAGER AS A BROKEN ONE**
            // (7820–7839). `OperatorAlerts` bounds how many times a day one kind
            // may push when an **inbound request** is what rang it, because a
            // stranger holding a tenant's public pixel key can otherwise ring the
            // bell once per tenant per quiet window. A withheld alert leaves both
            // timestamps null — the same pair of nulls a typo'd address leaves —
            // and this count's entire job is to catch **a channel that does not
            // work**. `push_withheld_at` is what tells the two apart, and it is a
            // column rather than an inference for exactly this reader.
            ->whereNull('push_withheld_at')
            // ⚠️ **`NULLS LAST` OUT LOUD, WHICH `ConventionsTest` REQUIRES AND
            // WHICH CAUGHT THIS FILE.** `fired_at` is NOT NULL on the creating
            // migration, so the plain `orderByDesc` was correct — and the lint is
            // right to refuse it anyway: Postgres sorts NULLs first on DESC, the
            // column's nullability is a fact in another file, and the day
            // somebody makes it nullable this listing would silently put the
            // undated rows first with nothing to notice.
            ->orderByRaw('fired_at DESC NULLS LAST')
            ->get()
            ->all();

        $this->line($raised.' alert(s) raised in the last '.WatchPlatformHealth::KEEP_DAYS.' days; '
            .count($undelivered).' of them reached no push channel.');

        foreach (array_slice($undelivered, 0, self::LISTED) as $alert) {
            $this->line('  '.$alert->fired_at->toDateTimeString().'  '
                .$alert->kind->value.($alert->subject === '' ? '' : ' ('.$alert->subject.')'));
        }

        if (count($undelivered) > self::LISTED) {
            $this->line('  ... and '.(count($undelivered) - self::LISTED).' more. '
                .'admin/operator-alerts is the screen that shows every one.');
        }
    }
}
