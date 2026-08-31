<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ops\ChannelProbeResult;
use App\Services\Ops\OperatorAlerts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Would a page actually arrive? — the question `ops:alert-channels` cannot ask.
 *
 * ⚠️ **THIS SENDS A REAL MESSAGE TO A REAL ADDRESS AND A REAL MOBILE NUMBER**,
 * and that is the whole of what it is for. `ops:alert-channels` reports both
 * registry rows as *set* and then says the honest thing — *"no alert has been
 * raised in the last 30 days, which reads the same whether nothing has gone
 * wrong or nothing is watching"* — and there was no way to move past that
 * sentence without something going wrong.
 *
 * ⛔ **THE OLD WAY WAS TO FAKE AN INCIDENT, AND THE COST WAS PERMANENT.**
 * {@see OperatorAlerts::raise()} writes to `operator_alerts` first and
 * unconditionally, and nothing in this application, its migrations or its
 * routes ever deletes from that table. A borrowed alert kind therefore left a
 * row on the board's *still ringing* section for a day, sorted to the top of
 * its severity band ahead of whatever real incident an operator should have
 * opened first, in the kind filter — and **inside `ops:alert-channels`' own
 * count of alerts that reached nobody**, where a channel test that failed
 * became indistinguishable from a real incident nobody was told about.
 * {@see OperatorAlerts::probeChannels()} writes no row.
 *
 * ## Deliberately not a deploy step, and there is a lint under that
 *
 * ⛔ **`ops:alert-channels` RUNS ON EVERY DEPLOY AND THIS MUST NEVER JOIN IT.**
 * That one reads two registry rows and a table; this one sends. A deploy that
 * texted the operator would page them for every deployment — 511's *"tuned
 * until nobody reads it"* with a real phone attached — and it would spend a
 * mail-ceiling slot and a carrier segment per deploy on a platform whose SES
 * account is still sandboxed at 200 messages a day.
 * `OperatorAlertingTest` fails the build if this name appears in
 * `composer.json`'s deploy script.
 *
 * ⚠️ **AND IT EXITS NON-ZERO WHEN THE PAGER IS BROKEN, WHICH IS NOT A BREACH OF
 * R25.** *A bell, never a brake* is about the alert path not being able to stop
 * the thing it was watching; nothing watches this and nothing runs it but a
 * person who typed its name. An exit code is the only answer a person can
 * script against, and 7243's argument for `ops:alert-channels` always exiting
 * zero was specifically that composer halts on the first non-zero script —
 * which is an argument about being a deploy step, not about being a command.
 *
 * ## What it proves, and the two things it does not
 *
 * ⛔ **HANDED OVER IS NOT DELIVERED.** SMTP accepting a message and a carrier
 * accepting a message are both one hop short of an inbox and a handset. A
 * bounce, a rejected recipient, a blocked sender and a phone in a drawer are
 * all downstream, this application can observe none of them (open question H),
 * and **the operator's own inbox is the only instrument for that half**.
 *
 * ⛔ **AND THE EMAIL HALF DELIBERATELY BYPASSES THE QUEUE.** A real alert email
 * is queued through `DeliverPlatformMail`; this calls `deliverNow()`, because a
 * probe that reported *"queued"* would answer the question with the question.
 * The consequence is stated rather than hidden: **a stopped queue worker would
 * not show up here and would stop a real alert email.** That failure has its
 * own bell — `OperatorAlertKind::HeartbeatSilent` — which is the one alert
 * absence raises.
 */
#[Signature('ops:alert-probe')]
#[Description('Send a test message down both operator alert channels and report whether they carried it')]
final class ProbeOperatorAlertChannels extends Command
{
    public function handle(OperatorAlerts $alerts): int
    {
        $this->line('Operator alert probe — sending one test message to whoever '
            .OperatorAlerts::EMAIL_KEY.' and '.OperatorAlerts::SMS_KEY.' name.');

        $probe = $alerts->probeChannels();

        foreach ($probe->results() as $result) {
            $this->report($result);
        }

        // ⚠️ **SAID EVERY RUN, BECAUSE IT IS THE PROPERTY THAT MAKES THIS SAFE
        // TO RUN AT ALL** — and the next person to reach for a fake alert will
        // be the one who does not know it. The alternative they would reach for
        // leaves a row nothing can remove.
        $this->line('No alert row was written and nothing was logged at critical level, so the alert '
            .'board, its still-ringing section, its kind filter and the undelivered count in '
            .'ops:alert-channels are all unchanged by this run.');

        return $this->conclude($probe->reachedSomebody(), $probe->anythingBroken());
    }

    /**
     * One channel's line — the outcome, and the reason when there is one.
     *
     * ⛔ **THE ADDRESS AND THE NUMBER ARE NEVER PRINTED** (7245), and the
     * failure text they might be embedded in is redacted upstream
     * ({@see OperatorAlerts::redacted()}). This output is read on a box and is
     * routinely piped into a file.
     */
    private function report(ChannelProbeResult $result): void
    {
        $key = $result->channel === 'email' ? OperatorAlerts::EMAIL_KEY : OperatorAlerts::SMS_KEY;

        $line = '  '.$key.': '.$result->outcome();

        if ($result->failure !== null) {
            // The class **and** the message, where the log line above gets only
            // the class: `MailNotDeliverable` alone has five causes and only its
            // text says which of them refused. A console is ephemeral and a log
            // is stored, which is the whole of the split.
            $line .= ' — '.$result->failure.($result->detail === null || $result->detail === ''
                ? ''
                : ': '.$result->detail);
        }

        // A configured channel that did not carry it, whether it threw or
        // declined — the two need the same eye and `isBroken()` is the one
        // place that judgement is made, so this cannot drift from the exit code.
        if ($result->isBroken()) {
            $this->warn($line);

            return;
        }

        $this->line($line);
    }

    /**
     * The verdict, and the exit code a person can script against.
     *
     * ⚠️ **A BLANK ROW IS NOT A FAILURE OF THAT CHANNEL** — blank is the off
     * switch and it is the only one ({@see OperatorAlerts::email()}). What is a
     * failure is a platform where **no** channel is configured, because
     * somebody who ran this asked to be paged and nothing can page them.
     */
    private function conclude(bool $reached, bool $broken): int
    {
        if (! $reached) {
            $this->warn('NOTHING CARRIED THE TEST MESSAGE. Nobody would be paged if this platform broke '
                .'right now. Set at least one of the two rows above in Ops settings, group Operations, '
                .'and fix any failure reported above it.');

            return self::FAILURE;
        }

        if ($broken) {
            $this->warn('One channel carried the test message and one did not. The pager still works, '
                .'and it works on one leg — see the failure above.');

            return self::FAILURE;
        }

        $this->info('Every configured channel carried the test message.');

        // ⛔ **THE HONEST LIMIT, PRINTED IMMEDIATELY UNDER THE GOOD NEWS.** A
        // line that stopped at the sentence above would be exactly the
        // reassurance 2496–2499 gave for two slices.
        // ⛔ **AND THE ONE CONDITION THIS LINE USED TO OMIT IS THE ONE THAT
        // WAS TRUE ON THE SHIPPED DEFAULT** (11460). It named a bounce, a
        // rejected recipient and a switched-off handset — every one of them a
        // fact about a real carrier — while `SMS_DRIVER=log` reaches no carrier
        // at all and printed *handed to the transport* above. That condition is
        // no longer in this branch: `PlatformTexter::alertOperator()` throws on
        // a driver that does not declare `App\Contracts\ReachesRecipients`, so
        // it now reports as **FAILED** with the driver named, and the sentence
        // below is once again a complete account of what is left unobserved.
        $this->line('That is the transport accepting it, which is not the message arriving. Check the '
            .'inbox and the handset — nothing in this application can observe a bounce, a rejected '
            .'recipient or a phone that is switched off. And the email went out synchronously, so a '
            .'stopped queue worker would not have shown here and would stop a real alert email.');

        return self::SUCCESS;
    }
}
