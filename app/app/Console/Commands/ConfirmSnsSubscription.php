<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SnsConfirmationOutcome;
use App\Http\Controllers\Mail\SesFeedbackController;
use App\Services\Mail\SnsSubscriptions;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Complete an SNS subscription that `POST /webhooks/ses` deliberately refused to
 * complete for itself (10220–10229).
 *
 * ⛔ **THIS EXISTS BECAUSE THE CONSOLE CLICK IT REPLACES DOES NOT.**
 * {@see SesFeedbackController} says a subscription is
 * *"confirmed by a person — one console click, once"*, and the SNS console's
 * *Confirm subscription* action asks for the **token**, which exists only inside
 * the POST body that controller received and refused to log. Measured on
 * production 2026-08-26: a verified confirmation arrived, the subscription sat
 * at `Pending confirmation`, and nothing in this application, this repository or
 * that console could finish it.
 *
 * ⚠️ **RUNNING IT IS THE DECISION, AND THE TOPIC ARN IS TYPED IN FULL.** There
 * is no `--all`, and naming no topic lists rather than acts. That friction is
 * the whole human step: this endpoint can suppress any email address on the
 * platform, so the thing that completes a subscription to it is a person who
 * read the topic and typed it, not a default and not a `yes`.
 *
 * ⚠️ **AND IT IS THE ONLY PROMPT THERE IS, DELIBERATELY.** A `confirm()` on top
 * of a typed ARN would add nothing a person could get wrong differently — and it
 * would make the command unusable under `--no-interaction`, which is how a
 * runbook step gets run with `--force` bolted on instead.
 */
#[Signature('mail:sns-subscriptions
    {topic? : The topic ARN to confirm. Omit to list what is waiting.}')]
#[Description('List SNS subscription confirmations waiting for an operator, or complete one')]
final class ConfirmSnsSubscription extends Command
{
    public function handle(SnsSubscriptions $subscriptions): int
    {
        $topic = $this->argument('topic');

        if (! is_string($topic) || trim($topic) === '') {
            return $this->showPending($subscriptions);
        }

        return $this->completeSubscription(trim($topic), $subscriptions);
    }

    /**
     * ⚠️ **AN EMPTY LIST HAS THREE CAUSES AND ALL THREE ARE PRINTED.** "Nothing
     * is waiting" reads as *"the feed is fine"* and it is far likelier to mean
     * *"nothing has been sent yet"*, *"it arrived more than an hour ago"* or
     * *"the allowlist is empty, so every delivery is being refused with a 401"*.
     * The first is the state of a deployment that has never wired SES up at all.
     */
    private function showPending(SnsSubscriptions $subscriptions): int
    {
        $pending = $subscriptions->pending();

        if ($pending === []) {
            $this->components->info('No SNS subscription confirmation is waiting.');

            $this->line('');
            $this->line('  That means one of three things:');
            $this->line('   - SNS has not sent one. In the AWS console, open the subscription and');
            $this->line('     choose "Request confirmation".');
            $this->line('   - One arrived and was held for longer than '.(SnsSubscriptions::pendingTtlSeconds() / 60)
                .' minutes. Request another.');
            $this->line('   - SES_SNS_TOPIC_ARNS is empty or names a different topic, in which case');
            $this->line('     every delivery to /webhooks/ses is being refused with a 401.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            '%d subscription confirmation%s waiting.',
            count($pending),
            count($pending) === 1 ? ' is' : 's are',
        ));

        foreach ($pending as $topicArn => $held) {
            $this->line('');
            $this->line('  '.$topicArn);
            $this->line('  arrived '.$this->ago($held['arrived_at']));
            $this->line('');
            $this->line('  Confirm it with:');
            $this->line('    php artisan mail:sns-subscriptions '.$topicArn);
        }

        // ⚠️ THE URL IS NOT PRINTED AND MUST NOT BE. It is a single-use
        // capability — whoever fetches it confirms the subscription — and a
        // terminal is a scrollback buffer, a screen share and a support ticket
        // paste. The controller refuses to put it in a log file for the same
        // reason; printing it here would move the refusal rather than keep it.

        return self::SUCCESS;
    }

    private function completeSubscription(string $topicArn, SnsSubscriptions $subscriptions): int
    {
        $outcome = $subscriptions->confirm($topicArn);

        return match ($outcome) {
            SnsConfirmationOutcome::Confirmed => $this->say(
                'Confirmed. Bounce, complaint and delivery events for this topic will now be '
                .'accepted at /webhooks/ses.',
                $topicArn,
                self::SUCCESS,
            ),

            SnsConfirmationOutcome::NothingPending => $this->say(
                'Nothing is waiting for this topic. Run this command with no arguments to see '
                .'what is, or choose "Request confirmation" on the subscription in the AWS console '
                .'and run this again.',
                $topicArn,
                self::FAILURE,
            ),

            SnsConfirmationOutcome::TopicNotAllowed => $this->say(
                'This deployment does not accept that topic. It is not in SES_SNS_TOPIC_ARNS, so '
                .'/webhooks/ses refuses every delivery from it with a 401 and confirming a '
                .'subscription to it would achieve nothing.',
                $topicArn,
                self::FAILURE,
            ),

            SnsConfirmationOutcome::Refused => $this->say(
                'AWS refused the confirmation. A token expires and is spent by a successful '
                .'confirmation, so this one will not start working: choose "Request confirmation" '
                .'on the subscription in the AWS console and run this again.',
                $topicArn,
                self::FAILURE,
            ),

            // ⚠️ THE ONE ARM WHERE RUNNING THE SAME COMMAND AGAIN IS THE REMEDY.
            // Nothing was presented to AWS, so nothing was spent and the held
            // confirmation is still there.
            SnsConfirmationOutcome::Unreachable => $this->say(
                'Could not reach AWS, so nothing was confirmed and nothing was spent. The '
                .'confirmation is still held — run this command again.',
                $topicArn,
                self::FAILURE,
            ),
        };
    }

    private function say(string $message, string $topicArn, int $status): int
    {
        $status === self::SUCCESS
            ? $this->components->info($message)
            : $this->components->error($message);

        $this->line('  '.$topicArn);

        return $status;
    }

    private function ago(CarbonImmutable $at): string
    {
        return $at->diffForHumans().' ('.$at->toIso8601String().')';
    }
}
