<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\MailNotDeliverable;
use App\Jobs\AutopilotJob;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Throwable;

/**
 * What may be written to the log about a platform email that did not go.
 *
 * ⛔ **A MAIL TRANSPORT'S EXCEPTION MESSAGE NAMES THE RECIPIENT, AND THIS
 * DEPLOYMENT'S DOES TODAY** (11170). Symfony builds
 * `', with message "…"'` from the server's verbatim reply
 * (`SmtpTransport::assertResponseCode()`), Laravel does not swallow it
 * (`Mailer::sendSymfonyMessage()` is `try`/`finally` with no `catch`), and
 * `CLAUDE.md` §Vendors records what this platform's own SES account answers:
 * `554 Message rejected: Email address is not verified` — **followed by the
 * identities that failed the check, which is the account holder's address.**
 * `Symfony\Component\Mime\Address::create()` is the second such class and
 * is unconditional: `Email "%s" does not comply with addr-spec of RFC 2822.`
 *
 * ⛔ **AND `getDebug()` IS WORSE THAN `getMessage()` AND MUST NEVER BE LOGGED.**
 * `TransportException::appendDebug()` is handed the whole SMTP conversation,
 * `RCPT TO:<address>` included. Nothing in `app/` reads it and nothing should.
 *
 * ## The three arms, and they are the point
 *
 * ⚠️ **A COMMENT, A GUARD OR A HELPER WHOSE ANSWER CANNOT VARY ACROSS ITS
 * SUBJECTS IS NOT WATCHING THEM**, which is the shape this class exists to
 * repair rather than merely the shape of the defect it replaces. So it answers
 * three different things about three different faults:
 *
 *   `MailNotDeliverable`          the full message. Every factory on that class
 *                                 is written in this repository and names our
 *                                 mailer, our registry rows, our sending domain
 *                                 or a count — {@see VendorLog}'s allowlist
 *                                 rule, satisfied because we wrote the string.
 *
 *   `UnexpectedResponseException` the SMTP reply CODE and no text at all. An
 *                                 SMTP reply code is three digits by
 *                                 construction — `sscanf($response, '%3d')` —
 *                                 so it is an allowlist of one integer field
 *                                 rather than a redaction of somebody else's
 *                                 sentence, and it cannot carry an address.
 *
 *   anything else                 the class name alone.
 *
 * ⛔ **NEVER A REDACTION ATTEMPT**, which is `App\Jobs\DeliverPlatformMail`'s
 * own ruling on this exact question: *"the transport arm gets null rather than a
 * redaction attempt, because a redaction that has to guess is 314–316 with a
 * customer's address inside it."* A denylist over a string somebody else's
 * server wrote fails open the first time that server rewords itself, and the
 * failure is invisible because the log looks fine.
 *
 * ## ⚠️ Why the code is carried when every sibling carries only the class
 *
 * `App\Console\Commands\SendOwnerWeeklyDigests` and
 * `App\Services\Ops\OperatorAlerts::email()` log `$e::class` and stop, and
 * that is safe and is not enough: `UnexpectedResponseException` is the class for
 * **every** SMTP refusal, so the class alone cannot tell a `421` an operator
 * should wait out from a `554` that will never succeed until somebody changes
 * something in the AWS console. **The code is the field that differs between the
 * two, and it is the field that carries no personal data.** A `0` means Symfony
 * parsed no code from the reply and is reported as null rather than as a code.
 */
final class MailFailure
{
    /**
     * The log context for a throwable out of a platform mail send.
     *
     * ⚠️ **BOTH KEYS ARE ALWAYS PRESENT AND AT MOST ONE IS EVER SET**, so a
     * null in the log line reads as *withheld on purpose* rather than as a
     * field somebody forgot to add.
     *
     * @return array{exception: class-string<Throwable>, smtp_code: int|null, reason: string|null}
     */
    public static function logContext(Throwable $e): array
    {
        return [
            'exception' => $e::class,
            'smtp_code' => self::smtpCode($e),
            'reason' => $e instanceof MailNotDeliverable ? $e->getMessage() : null,
        ];
    }

    /**
     * The safest honest string for a **record**, where a log context cannot go.
     *
     * ⛔ **ASKED ABOUT EVERY AUTOMATION'S EXCEPTION AND NOT ONLY A MAIL ONE**
     * (11333). {@see AutopilotJob::handle()} is `final`, so the one `text`
     * column it writes is written for **every class that extends
     * `AutopilotJob`** — carriers, AI providers, decoders and mail servers —
     * and this is asked about all of them.
     * ⚠️ **NO COUNT HERE, DELIBERATELY** (11497). This read *"forty-three job
     * classes"*, which is the whole of `app/Jobs` rather than the subclasses
     * `final handle()` confines the column to — **a population 54% larger than
     * the real one, in the file whose own subject is an answer that cannot vary
     * across its subjects.** The set grows every wave, the property does not,
     * and `grep -rn 'extends AutopilotJob' app/` is one command away.
     * ⚠️ **THE CLASS NAME IS THEREFORE NARROWER THAN THE SUBJECT, AND IT IS
     * RECORDED RATHER THAN RENAMED**: the two console senders that call
     * {@see self::logContext()} are outside the slice that added this method,
     * and a second class restating these arms would be two copies of one rule.
     *
     * ## ⛔ AN ALLOWLIST OF TWO, DEFAULTING TO THE CLASS
     *
     * It recognises exactly two families and answers the class name for
     * everything else — including every type it has never heard of, which is
     * the arm that must be safe and is the arm a denylist gets wrong.
     *
     *   {@see MailNotDeliverable}         the full message. Every factory is
     *                                     written in this repository; the three
     *                                     that embed an address embed **our own
     *                                     from-address**, and the messages name
     *                                     the registry row an operator has to
     *                                     set. `UrgentEscalationTest`'s *"a page
     *                                     that reached nobody is a failed run"*
     *                                     reads exactly that and is why this arm
     *                                     exists rather than collapsing to the
     *                                     class (11334).
     *
     *   {@see UnexpectedResponseException} the class and the three-digit reply
     *                                     code, never the text. The code is what
     *                                     separates a `421` an operator waits out
     *                                     from a `554` that will never succeed
     *                                     until somebody changes something in the
     *                                     AWS console, and it is the field that
     *                                     carries no personal data.
     *
     *   anything else                     the class name alone.
     *
     * ⛔ **`App\Exceptions\…` IS NOT A SAFETY PROPERTY AND MUST NEVER BECOME
     * THIS METHOD'S TEST** (11335). A project exception can carry a third
     * party's string: `InfobipClient` raises
     * `TextNotDeliverable::notConfigured($e->getMessage())` and `CampaignPacks`
     * interpolates a caught `getMessage()` into a `CampaignRefused`. **Being
     * ours is a fact about the namespace; being written here is a fact about
     * every factory on the class**, and only the second is what licenses the
     * first arm above.
     */
    public static function runError(Throwable $e): string
    {
        if ($e instanceof MailNotDeliverable) {
            return $e->getMessage();
        }

        $code = self::smtpCode($e);

        return $code === null ? $e::class : $e::class." (SMTP {$code})";
    }

    /**
     * The three-digit SMTP reply code behind a refusal, or null.
     *
     * ⚠️ **THIS CLASS AND NO PARENT.** `TransportException` is raised for a
     * socket that never opened and for a STARTTLS handshake that failed, and on
     * those arms `getCode()` is the constructor default rather than anything a
     * server said. Only {@see UnexpectedResponseException} is constructed with
     * the parsed reply code, so only it is asked.
     */
    private static function smtpCode(Throwable $e): ?int
    {
        if (! $e instanceof UnexpectedResponseException) {
            return null;
        }

        $code = $e->getCode();

        // The whole SMTP reply-code space. Anything outside it — including the
        // `0` Symfony uses for "no code could be parsed from the reply" — is
        // not a code and is not reported as one.
        return $code >= 100 && $code <= 599 ? $code : null;
    }
}
