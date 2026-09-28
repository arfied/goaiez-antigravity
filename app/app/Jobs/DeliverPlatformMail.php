<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AlertOrigin;
use App\Enums\OperatorAlertKind;
use App\Exceptions\MailNotDeliverable;
use App\Services\Mail\MailDrivers;
use App\Services\Mail\PlatformMailer;
use App\Services\Mail\PlatformMailIdentity;
use App\Services\Ops\OperatorAlerts;
use App\Services\Ops\PlatformHealthChecks;
use App\Support\CredentialManifest;
use App\Support\QueueBackoff;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One platform email, delivered off the request.
 *
 * ⚠️ **THE GUARD LIVES HERE RATHER THAN IN `PlatformMailer::send()`, AND THAT IS
 * A CORRECTNESS REQUIREMENT.** Two separate things break if
 * `assertDeliverable()` runs synchronously at the call site:
 *
 *   1. **The login page starts returning 500s.** `MagicLinkService::request()`
 *      is unauthenticated and is reached by anybody typing an address into the
 *      sign-in form. A misconfigured mailer is an operations problem; it must
 *      not become an outage of the page people use to report it.
 *
 *   2. **The mailer's configuration becomes an account-enumeration oracle**, and
 *      this is the one that makes it a security question rather than a taste
 *      one. `request()` returns early and silently when the address has no
 *      account, and only reaches a send when it does — so a synchronous throw
 *      answers *"does this address belong to somebody"* with a 500 for yes and a
 *      200 for no. That is precisely the property `MagicLinkService`'s docblock
 *      calls out as the reason the endpoint behaves identically either way, and
 *      it would have been undone by a guard added three files away for an
 *      unrelated reason.
 *
 * So callers get a queue push that cannot fail on them, and the refusal happens
 * where an operator can see it — a failed job carrying a sentence naming the
 * environment variable to set.
 *
 * ⛔ **"A QUEUE PUSH THAT CANNOT FAIL ON THEM" WAS A CLAIM ABOUT ONE EXCEPTION
 * TYPE ON ONE QUEUE DRIVER, AND THE CONTAINMENT THAT MAKES IT TRUE IS NOT IN
 * THIS FILE — CORRECTED 2026-08-25 (9500–9503).** {@see self::handle()}'s
 * `catch` names {@see MailNotDeliverable} and nothing else, and
 * `SyncQueue::handleException()` calls `$job->fail($e)` and then **rethrows**
 * — so on `QUEUE_CONNECTION=sync` a Symfony `TransportException` walked back
 * out of `dispatch()` into the caller and 702's oracle was open again by a
 * different door. ⚠️ **The paragraphs above are kept and are unchanged in their
 * argument**; what they over-claimed is a property they attributed to this
 * class and this class cannot have.
 *
 * ⛔ **AND IT MUST NOT ACQUIRE IT.** Widening the `catch` below to `Throwable`
 * closes the leak and **deletes the retry ladder** — `fail()` skips the
 * remaining attempts, which is right for a configuration refusal and wrong for
 * the refused SMTP connection `$tries` was written for. So the containment sits
 * in {@see PlatformMailer::send()}, where the promise was made: this method goes
 * on throwing, a worker goes on retrying, {@see self::failed()} goes on ringing
 * on the last attempt, and the caller is never told on any driver.
 * `PlatformMailerTest`'s *"a transient transport fault is still retried three
 * times on a real queue"* drives that on the `database` connection with a real
 * worker, because `sync` has no ladder to observe at all.
 *
 * ⛔ **"WHERE AN OPERATOR CAN SEE IT" WAS A CLAIM ABOUT A `failed_jobs` ROW
 * NOTHING PUSHED, AND IT WAS FALSE FOR THE WHOLE LIFE OF THIS CLASS — CORRECTED
 * 2026-08-25 (9370–9379).** The paragraph above is kept because its argument
 * about the *split* is unchanged and correct; what it over-claimed is the
 * second half of the sentence. **`failed_jobs` is a table an operator has to
 * decide to look in**, and the only alerting reader of it —
 * {@see PlatformHealthChecks} — counts rows against
 * `ops.failed_job_spike`, which seeds at 25 in an hour. ⛔ **Production carried
 * three consecutive permanent failures of this exact class, eighteen minutes
 * apart, and they sat unread for five days**: three is not twenty-five, so
 * nothing rang, and one of the three was the `log` transport accepting a
 * message and dropping it. ⚠️ **And if any of the three had been an operator
 * alert email, the only channel that would have reported the mail path was the
 * mail path.** ✅ **{@see self::failed()} is the push**, and it is on this class
 * rather than on a sweep because this is where the fault is known.
 *
 * NOT AN `AutopilotJob`. That base class establishes a tenant, claims an
 * idempotency key and chooses between `execute()` and `handoff()`, all of which
 * are the right machinery for an automation acting on a tenant's behalf. This is
 * platform mail to an account holder: there is no tenant, no automation toggle
 * to respect and no Google-shaped fallback. Extending it to inherit the retry
 * ladder would mean answering three questions that do not apply.
 *
 * ⚠️ **THE NOTIFICATION IS SERIALISED INTO THE QUEUE PAYLOAD, INCLUDING
 * `MagicLinkLogin`'S PLAINTEXT URL.** That is true today and was true before
 * this class existed — `MagicLinkLogin` has always implemented `ShouldQueue`, so
 * the token has always been written into `jobs.payload` under
 * `QUEUE_CONNECTION=database`. Decision 704 records it, corrects the docblock
 * that said otherwise, and states the mitigation: the token is dead fifteen
 * minutes after it is minted, so a retained payload carries an expired secret.
 */
final class DeliverPlatformMail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Three attempts.
     *
     * A transient SMTP failure is ordinary and worth retrying; a misconfigured
     * mailer is not, and will simply fail three times and land where it can be
     * read. One attempt would make the first case lose real mail, and the cron
     * worker deliberately passes no `--tries` (`CLAUDE.md` §Key commands), so a
     * job that wants more than one has to say so itself.
     */
    public int $tries = 3;

    public function backoff(): array
    {
        return QueueBackoff::ladder(QueueBackoff::fromSetting('queue.backoff.standard_seconds'));
    }

    /**
     * ⚠️ **THE IDENTITY IS SERIALISED INTO THE PAYLOAD AND CARRIES NO PERSONAL
     * DATA.** A business name, an eight-character code and a business id — the
     * tenant's own trading name, which is public, a code the migration is
     * explicit is not a secret, and the tenant's own primary key. It is
     * deliberately not the `Customer`, the address or the permit: a queue
     * payload outlives the row it describes, and the address is already in
     * `$address` for the one reason it has to be.
     *
     * ⛔ **AND IT NEVER CARRIES THE CAN-SPAM FOOTER** (T176 P21). That object
     * holds a sealed unsubscribe token whose plaintext *is* the recipient's
     * address, so it is minted in `PlatformMailer::deliverNow()` — after this
     * hop — rather than travelling here into `jobs` and on into `failed_jobs`.
     * `CanSpamMailTest` asserts `$identity->footer === null` on the pushed job,
     * so the claim is checked rather than remembered.
     */
    public function __construct(
        private readonly string $address,
        private readonly Notification $notification,
        private readonly ?PlatformMailIdentity $identity = null,
    ) {}

    public function handle(PlatformMailer $mailer): void
    {
        try {
            $mailer->deliverNow($this->address, $this->notification, $this->identity);
        } catch (MailNotDeliverable $e) {
            // ⚠️ **`fail()` RATHER THAN LETTING IT THROW, AND THE SUITE IS WHAT
            // PROVED THIS NECESSARY** (709). Dispatching does not mean leaving
            // the request: under `QUEUE_CONNECTION=sync` — a legitimate
            // small-deployment choice and what the test suite runs —
            // `SyncQueue` executes the job in-process and re-raises anything it
            // throws **to the caller**. So a throw here reaches
            // `MagicLinkService::request()` after all, and both halves of
            // decision 702 come back: the login page 500s, and it 500s only for
            // addresses that have an account. `LoginMethodsTest`'s
            // *"asking for a link never reveals whether the account exists"*
            // failed on exactly that, which is the pre-existing test doing the
            // job it was written for.
            //
            // `fail()` records the failure and returns, on every driver. The
            // caller is untouched whatever the queue is configured to be, which
            // is what makes 702 a property of the code rather than of one
            // environment's `.env`.
            //
            // ⛔ **FOR THIS EXCEPTION TYPE AND NO OTHER, WHICH IS WHAT NOBODY
            // WROTE DOWN UNTIL 9500 AND WHAT THE SENTENCE ABOVE READS AS
            // COVERING.** A Symfony `TransportException` is not
            // `MailNotDeliverable`, is the commonest real fault on this path,
            // and went straight past this `catch` into `SyncQueue`'s rethrow.
            // ⛔ **AND IT MUST GO ON DOING SO** — see the class docblock: the
            // `catch` is deliberately *not* widened, because widening it deletes
            // the ladder `$tries` exists for, and the containment lives at
            // {@see PlatformMailer::send()} instead. ⚠️ **709's clause about
            // `Laravel's default` is dropped from the sentence above rather than
            // repeated**: this repository's `config/queue.php` and its
            // `.env.example` both say `database`, and so does the framework's
            // own shipped config.
            //
            // It also **skips the retries**, correctly: `$tries` exists for a
            // refused SMTP connection, and re-attempting a configuration error
            // twice more changes nothing except copying the payload — which for
            // `MagicLinkLogin` means three copies of a sign-in URL instead of
            // one (704).
            Log::error('Platform mail was not deliverable.', [
                // ⚠️ The address is deliberately absent. This is the one line
                // that would put a customer's email address in the log file for
                // every send, and `failed_jobs` already holds the payload for
                // whoever is actually diagnosing it.
                //
                // ⚠️ **AND THAT TRADE IS WHY `failed_jobs`' HORIZON IS THIRTY
                // DAYS RATHER THAN A WEEK** (8610-8639). This comment is one of
                // nine places in `app/` that reason from the payload being
                // there; `PruneFailedJobs` weighs all nine against the fact that
                // the payload is also the least protected copy of a customer's
                // address in the schema. The log line stays as it is — what
                // changed is that the copy this sentence relies on now expires.
                'reason' => $e->getMessage(),
                'notification' => $this->notification::class,
            ]);

            $this->fail($e);
        }
    }

    /**
     * The queue has finished with this message and it never went (9370–9379).
     *
     * ## ⛔ Why the raise is here and not in a sweep
     *
     * ⛔ **THIS IS THE ONE PLACE THAT KNOWS.** A sweep over `failed_jobs` would
     * have to read the serialised payload to find out which failures were mail,
     * and it would still be counting **traffic** — see
     * {@see OperatorAlertKind::PlatformMailUndeliverable} for why no figure over
     * a window can arm a bell about a path that carries three messages an hour.
     * The framework calls this method once per permanent failure, with the
     * exception, and that is the fact rather than a proxy for it.
     *
     * ⚠️ **IT IS REACHED BY BOTH FAILURE MODES AND THAT WAS CHECKED RATHER THAN
     * ASSUMED.** `handle()` catches `MailNotDeliverable` and calls `fail()`,
     * which is `InteractsWithQueue::fail()` → `Job::fail()` →
     * `CallQueuedHandler::failed()` → `$command->failed($e)`; an uncaught
     * transport exception reaches the same method after the third attempt. **The
     * three production rows are the second kind** — an SMTP authentication
     * failure is a Symfony `TransportException` and `handle()` has never caught
     * one.
     *
     * ⚠️ **AND 9500's CONTAINMENT IN {@see PlatformMailer::send()} DOES NOT
     * TAKE THIS AWAY, WHICH IS THE ONE THING THAT HAD TO BE CHECKED ABOUT IT.**
     * On `sync` the framework fails the job **before** it rethrows, so this
     * method has already run by the time `send()` catches; on any real driver
     * `send()`'s `catch` is not reached at all and the worker's own path is
     * unchanged. Both are asserted rather than reasoned about —
     * `PlatformMailerTest`'s *"the caller is not told, on the arm that is not
     * `MailNotDeliverable`"* asserts the bell **in the same test** as the
     * silence, because a `catch` that swallowed the report would satisfy the
     * silence half on its own.
     *
     * ## ⛔ A bell, never a brake, and never a loop
     *
     * ⛔ **EVERYTHING IN HERE IS CONTAINED** (R25). A job that has already
     * failed must not fail differently because the pager was unreachable, and
     * this method is called from inside the queue's own failure handling, where
     * a throw is somebody else's problem to explain.
     *
     * ⛔ **AND IT CANNOT RING ITSELF.** {@see OperatorAlerts::email()} sends
     * through {@see PlatformMailer::deliverNow()} rather than dispatching this
     * job, so a broken mail path refuses the alert email **synchronously**,
     * inside that method's own `catch`, and leaves `emailed_at` null. There is
     * no second failed job and therefore no second call to this method. ⚠️ **It
     * dispatched before 2026-08-25 and the recursion was real then**: the alert
     * about broken mail would have been queued, failed three times, and called
     * this method again — terminating on the dedupe, having written three more
     * rows into `failed_jobs` about a mail path that was already down.
     *
     * ## ⚠️ The subject is the mailer, and the two arms carry different amounts
     *
     * ⚠️ **PER MAILER, WHICH IS THE THING AN OPERATOR GOES AND FIXES** — not per
     * notification class, which would be one bell per kind of message about one
     * broken transport. The dedupe is on `(kind, subject)`, so the second and
     * third failures of the same mailer inside the window are one incident,
     * which is exactly what the three production rows were.
     */
    public function failed(?Throwable $exception): void
    {
        try {
            $alerts = app(OperatorAlerts::class);
            $mailer = app(MailDrivers::class)->active();

            if ($alerts->rangSince(
                OperatorAlertKind::PlatformMailUndeliverable,
                $mailer,
                CarbonImmutable::now()->subHours($alerts->mailPathRepeatHours()),
            )) {
                return;
            }

            $alerts->raise(
                OperatorAlertKind::PlatformMailUndeliverable,
                $mailer,
                self::alertSummary($mailer, $exception),
                [
                    'mailer' => $mailer,
                    // ⚠️ **THE CLASS, NEVER THE ADDRESS.** `$this->address` is
                    // an account holder's own email and the whole reason
                    // `handle()`'s log line withholds it; `context` is rendered
                    // on the alert board and read back by an incident review.
                    'notification' => $this->notification::class,
                    'fault' => $exception instanceof MailNotDeliverable ? 'refused' : 'transport',
                    // ⛔ **OURS OR NOTHING.** Every `MailNotDeliverable`
                    // message is written in this repository and names a mailer,
                    // a registry row, a domain or a count. A transport's message
                    // is written by somebody else's server and its ordinary
                    // shape carries the recipient — `550 <name@example.com>:
                    // Recipient address rejected` — and this value is rendered
                    // on the alert board and spread into a `critical` log line.
                    // **The transport arm gets null rather than a redaction
                    // attempt**, because a redaction that has to guess is
                    // 314–316 with a customer's address inside it.
                    'reason' => $exception instanceof MailNotDeliverable
                        ? $exception->getMessage()
                        : null,
                    'repeat_quiet_hours' => $alerts->mailPathRepeatHours(),
                ],
                // ⛔ **STATED RATHER THAN DETECTED, ON
                // {@see \App\Services\Ops\PlatformHealthChecks}' REASONING.**
                // Under `QUEUE_CONNECTION=sync` — a legitimate small-deployment
                // choice, and what the suite runs — this executes inside
                // whatever request happened to send the message, so
                // `AlertOrigin::detected()` would read a stranger typing an
                // address into the sign-in form as the ringer and bound the
                // push. ⚠️ **And the bound it would apply is already there and
                // is tighter**: `MAIL_PATH_REPEAT_HOURS` allows one push per
                // mailer per day, where the budget allows ten, so declaring
                // this `Platform` costs nothing a flood could exploit.
                origin: AlertOrigin::Platform,
            );
        } catch (Throwable $e) {
            Log::error('platform mail failure could not be reported to the operator', [
                'exception' => $e::class,
                'notification' => $this->notification::class,
            ]);
        }
    }

    /**
     * What the operator is told, and it is two sentences because it is two
     * faults.
     *
     * ⛔ **NEITHER ARM CLAIMS THE WHOLE PLATFORM IS STOPPED, AND BOTH DRAFTS
     * DID.** The refused arm looks platform-wide — a `log` transport, no from
     * address, an unstated ceiling — and **three of its causes are per
     * message**: `noPostalAddress()`, `commercialWithoutOptOut()` and
     * `unclassifiedNotification()` refuse one notification and leave every other
     * message going out normally. The transport arm cannot tell an
     * authentication failure that stops everything from one rejected recipient.
     * **So each sentence states what its own arm actually knows**, which is that
     * a message was refused or not accepted, was never sent, and will not be
     * tried again.
     *
     * ⛔ **THE REASON IS NOT IN THE SUMMARY AND THAT IS A LENGTH FACT RATHER
     * THAN A PREFERENCE.** `MailNotDeliverable`'s messages run to four hundred
     * and thirty characters — they are operator-facing configuration
     * instructions naming registry rows and decision numbers — so inlining one
     * would clamp on every firing, log
     * {@see OperatorAlerts}' *"summary did not fit the column"* warning as
     * though somebody had written a careless sentence, and eat its own middle.
     * **It goes in `context` instead**, which is rendered on the alert board and
     * spread into the `critical` log line by {@see OperatorAlerts}, and the
     * summary carries what a handset needs: what happened, that nothing retries
     * it, and where to look.
     *
     * ⛔ **AND THE TRANSPORT ARM CARRIES ONLY A CLASS NAME, WHICH IS A PRIVACY
     * RULE RATHER THAN A STYLE.** Every `MailNotDeliverable` message is written
     * by us and names a mailer, a registry key, a domain or a count —
     * `raise()`'s rule exactly. **A transport's message is written by somebody
     * else's server**, and `550 <name@example.com>: Recipient address rejected`
     * is its ordinary shape, so repeating it would put a customer's address into
     * `operator_alerts.summary`, into a `critical` log line, into an email and
     * into a text message. {@see OperatorAlerts::redacted()} removes the
     * operator's own address and nobody else's, and it is not on this path.
     * ⚠️ **The short class name**, because the qualified one is fifty-four
     * characters of namespace on a line read on a handset.
     *
     * ⚠️ **THE TRANSPORT ARM'S CLOSING SENTENCE IS THIS SLICE'S OTHER FINDING,
     * PUT WHERE SOMEBODY MEETS IT** (9374). `MAIL_USERNAME` and `MAIL_PASSWORD`
     * are read by `config/mail.php` straight from the environment and appear in
     * {@see CredentialManifest} — and therefore on
     * `Admin\Credentials`, and therefore in wave 26's credential bell —
     * **nowhere at all**. An operator sent to the Credentials screen by an SMTP
     * authentication failure would find every key present and green.
     */
    private static function alertSummary(string $mailer, ?Throwable $exception): string
    {
        if ($exception instanceof MailNotDeliverable) {
            return 'A platform email was refused before it reached the transport, so it was never '
                .'sent and nothing retries it. The refusal is this platform\'s own rather than the '
                ."{$mailer} mailer's, and the reason is recorded with this alert. Ops, Platform, "
                .'Email sending shows what the mail path is doing.';
        }

        $fault = $exception === null ? 'and gave no reason' : '('.class_basename($exception).')';

        return "A platform email was handed to the {$mailer} mailer and was not delivered {$fault}. "
            .'Nothing retries it and the recipient was told nothing. If this mailer signs in, its '
            ."username and password are in this server's .env and on no register here, so Ops "
            .'cannot tell you they are wrong.';
    }
}
