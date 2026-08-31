<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\MailNotDeliverable;
use App\Notifications\SesFeedbackProbe;
use App\Services\Mail\MailSendRate;
use App\Services\Mail\PlatformMailer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * The only supported way to exercise the `Notification` half of open question H
 * against real AWS bytes (10280–10289).
 *
 * ⛔ **RUNNING THIS COMMAND IS WHAT CLOSED THE GAP `SnsMessageVerifier`'s
 * DOCBLOCK NAMED — CORRECTED 2026-08-27 (10460–10479).** The
 * `SubscriptionConfirmation` field list verified against production traffic
 * on 2026-08-26; the `Notification` field list — the one that carries every
 * genuine bounce and complaint — verified the same day, against real AWS
 * bytes, through three probes sent by this command: a bounce, and a
 * complaint and a delivery sharing one timestamp, all genuine and all
 * verified. AWS's own mailbox simulator is sandbox-exempt and answers with a
 * genuine, AWS-signed `Notification` — this command is the only supported
 * way to reach it, and it is what a fresh deployment still needs to run
 * before this paragraph is true for it too. ⛔ **What running it does not
 * prove**: a real bounce from a real mailbox provider, rather than AWS's own
 * simulator, is a different payload and stays unproven until SES production
 * access and a real recipient exist.
 *
 * ## The refusal is the whole design
 *
 * ⛔ **IT WILL NOT SEND ANYWHERE EXCEPT ONE OF AWS'S OWN FIVE ADDRESSES.** A
 * general "send a mail to anyone" command on a platform holding end-customer
 * addresses is a support surface and a data-exfiltration path in the same
 * breath — this refuses everything else, checked against
 * `config('platform_mail.ses_simulator.addresses')` rather than against a
 * pattern, because the whole set is five fixed strings AWS operates and there
 * is nothing to pattern-match. ⚠️ **Emptying that config refuses too** — the
 * same fail-closed reading `SnsMessageVerifier` gives its own allowlist: a
 * misconfigured deployment leaves this inert rather than open.
 *
 * ⛔ **AND IT WILL NOT SEND UNLESS `MAIL_MAILER` IS `smtp`.** AWS's own page is
 * explicit: *"you must send it through Amazon SES, by using the AWS CLI, an AWS
 * SDK, the Amazon SES console, the Amazon SES SMTP interface, or the Amazon SES
 * API. The mailbox simulator doesn't respond to emails that it receives from
 * external sources."* `PlatformMailer::assertDeliverable()` alone would accept
 * `gmail` as "delivers something" — Workspace is a real transport — but a
 * message sent through Workspace to `bounce@simulator.amazonses.com` is mail to
 * an unrelated inbox that never touches Amazon SES at all, and the simulator
 * would never answer it. This command's own check is narrower than the
 * mailer's own guard, on purpose, for exactly the vendor this feature is about.
 *
 * ## What it spends, corrected against AWS's own page rather than assumed
 *
 * ⚠️ **BILLED THE SAME AS ANY OTHER MESSAGE, AND NOT COUNTED AGAINST THE DAILY
 * SENDING QUOTA.** Verified against
 * docs.aws.amazon.com/ses/latest/dg/send-an-email-from-console.html, read
 * 2026-08-26: *"For billing purposes, emails that you send to the Amazon SES
 * mailbox simulator are the same as any other email you send"* and
 * *"they don't affect your daily sending quota"* — though the same page says
 * they ARE bound by the account's maximum sending RATE, which is
 * {@see MailSendRate}'s own registry row for this mailer.
 * One run of this command is real money and one real send against the rate
 * limit; it is not real quota, which an earlier reading of this feature
 * assumed without checking.
 *
 * ## What running it proves, and what it does not
 *
 * ⛔ **AN ACCEPT FROM SES IS THE TRANSPORT WORKING, AND NOTHING ELSE.** It says
 * this application can compose a message and hand it to SES over SMTP with the
 * credentials configured. It says NOTHING about whether a `Notification` comes
 * back, whether it verifies, or whether `MailFeedback` understands it —
 * {@see MailFeedbackStatus} is the reading half, run a
 * few minutes later.
 */
#[Signature('mail:probe-ses-simulator
    {address : One of AWS\'s own mailbox simulator addresses — see the list this command refuses everything else against}')]
#[Description('Send one deliberate message to an AWS SES mailbox simulator address, to prove the transport reaches SES')]
final class ProbeSesSimulator extends Command
{
    public function handle(PlatformMailer $mailer): int
    {
        $address = mb_strtolower(trim((string) $this->argument('address')));

        $scenario = $this->scenarioFor($address);

        if ($scenario === null) {
            $this->components->error("Refusing. '{$address}' is not one of the addresses this command "
                .'will send to.');
            $this->printAllowlist();

            return self::FAILURE;
        }

        $mailerName = (string) config('mail.default');

        if ($mailerName !== 'smtp') {
            $this->components->error("Refusing. MAIL_MAILER is '{$mailerName}', not 'smtp'. AWS's own "
                .'mailbox simulator only answers a message sent through Amazon SES itself — through '
                .'any other transport this would be an ordinary email to an address that never '
                .'delivers and never answers.');

            return self::FAILURE;
        }

        $this->line("Sending one message to {$address} (scenario: {$scenario}) through the '{$mailerName}' mailer.");

        try {
            $mailer->deliverNow($address, new SesFeedbackProbe($scenario));
        } catch (MailNotDeliverable $e) {
            $this->components->error('The mail system refused to send at all: '.$e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            // ⚠️ **EVERYTHING ELSE IS THE TRANSPORT ITSELF REFUSING**, on
            // `ProbeOperatorAlertChannels::report()`'s own reasoning: a
            // connection refused, a certificate error, an SES 4xx. This is the
            // one output line that can carry vendor detail, because it is read
            // by the operator who just typed the command, on the box that sent
            // it — not stored, not logged twice, and never seen by anyone else.
            //
            // ⛔ **AND THE SURFACE IS THE SECOND HALF OF THE ARGUMENT, NEVER THE
            // FIRST — WHICH IS WHAT STOPS IT TRANSFERRING** (11498). The first
            // half is the subject: `scenarioFor()` above has already refused
            // every address except AWS's own five simulator mailboxes, so the
            // recipient this message names is **not a person** and cannot be.
            // That is what makes the widest arms safe here and nowhere else —
            // `UnexpectedResponseException` carries SES's verbatim reply, which
            // {@see MailFailure} records as naming *the identities that failed
            // the check*, and `deliverNow()` reads the registry and writes the
            // quota row either side of the transport, so a `QueryException`
            // interpolating its own bindings
            // (`QueryException::formatMessage()`) is reachable here too. Both
            // are harmless only because of the allowlist — the identity SES
            // would name is our own sending domain, and the bindings are
            // registry keys. ⚠️ **Written as a console-surface
            // argument alone, this comment reads as a licence for any command
            // an operator types**, and this codebase has two open findings of
            // exactly that shape (11339, 11491).
            $this->components->error('SES did not accept the message: '.$e::class.' — '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('SES accepted the message.');
        $this->line('');
        $this->line('  That proves the transport: this application can compose a message and hand it');
        $this->line('  to SES over SMTP with the configured credentials.');
        $this->line('');
        $this->line('  It proves NOTHING about whether a Notification comes back, whether it');
        $this->line('  verifies, or whether MailFeedback understands it. AWS answers this address');
        $this->line('  scenario with a synthetic SNS notification within moments — check for it with:');
        $this->line('');
        $this->line('    php artisan mail:feedback-status');
        $this->line('');
        $this->line('  run a few minutes from now, not immediately.');

        return self::SUCCESS;
    }

    private function scenarioFor(string $address): ?string
    {
        $addresses = config('platform_mail.ses_simulator.addresses');

        if (! is_array($addresses) || $addresses === []) {
            return null;
        }

        foreach ($addresses as $scenario => $simulatorAddress) {
            if (is_string($simulatorAddress) && mb_strtolower($simulatorAddress) === $address) {
                return (string) $scenario;
            }
        }

        return null;
    }

    private function printAllowlist(): void
    {
        $addresses = config('platform_mail.ses_simulator.addresses');

        if (! is_array($addresses) || $addresses === []) {
            $this->line('  No simulator addresses are configured at all — refusing everything.');

            return;
        }

        $this->line('  Send to one of:');

        foreach ($addresses as $simulatorAddress) {
            if (is_string($simulatorAddress)) {
                $this->line('    '.$simulatorAddress);
            }
        }
    }
}
