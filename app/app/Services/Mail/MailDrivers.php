<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Enums\MailFeedbackSignal;

/**
 * Which transport is carrying our mail, and what it can tell us afterwards.
 *
 * ⚠️ **THIS IS THE SEAM T137 R3 PROMISES, AND THE PROMISE IS FALSIFIABLE.**
 * R3's words are *"the multi-driver seam keeps SES slot-in ready with zero code
 * change when volume grows"*, and 2093 records that reversibility as *"the part
 * of the design that makes this survivable"*. A seam asserted in a docblock is
 * 314-316's shape; what makes this one real is that the SES driver needs **no
 * class, no mailer block and no branch** — SES is SMTP, so slotting it in is
 * `MAIL_MAILER=smtp` with SES's host and credentials, and the only thing this
 * file knows about it is that the resulting feedback signal is typed once the
 * SNS topic is subscribed.
 *
 * ⚠️ **THE SIGNAL IS PER MAILER AND NOT PER TRANSPORT**, which is
 * `PlatformMailer::UNDELIVERABLE`'s distinction inverted and for the opposite
 * reason. There, a mailer key could not be trusted because an operator names it
 * whatever they like and the *transport* is the fact. Here the transport is not
 * the fact: two `smtp` mailers can point at two relays, one with a bounce
 * webhook wired and one without, and the mailer name is the only thing that
 * tells them apart.
 *
 * ⚠️ **AN UNRECOGNISED MAILER IS `None`.** 486's rule — the safe-looking
 * default is the permissive one — applied where the value decides whether this
 * application may email somebody else's customer.
 *
 * ⚠️ **AND SINCE R16, `typed` IS A CLAIM THIS CLASS CHECKS RATHER THAN TAKES.**
 * The flip to SES-primary makes `PLATFORM_MAIL_SMTP_FEEDBACK=typed` a line
 * somebody types on a deployment for the first time, and it is the only value
 * that opens {@see PlatformMailer::sendToCustomer()}. But
 * the *feed* it names is `POST /webhooks/ses`, and that endpoint refuses every
 * message — genuine ones included — while `platform_mail.sns.topic_arns` is
 * empty, because an unconfigured allowlist is the fail-closed branch
 * `SnsMessageVerifier` deliberately takes. So the two settings are one fact in
 * two files, and setting the first without the second produces the exact state
 * open question H exists to prevent: **mail to somebody else's customers whose
 * every bounce and complaint arrives at a 401 and suppresses nobody**, with a
 * send gate reading as satisfied throughout. That is 314-316's shape — a
 * protection asserted before it is true — reachable by one `.env` line, so the
 * claim is verified here instead of trusted.
 */
final class MailDrivers
{
    /**
     * Every mailer this application can actually be pointed at.
     *
     * ⚠️ **NOT EVERY BLOCK IN `config/mail.php`.** The framework skeleton ships
     * `ses`, `postmark`, `resend`, `sendmail`, `failover` and `roundrobin` as
     * examples, and 4435 records why the first of those is a trap here rather
     * than an option. **SES is reached as plain `smtp`.** These four are the
     * ones a deployment is expected to select, and `PlatformMailerTest`'s
     * *"every mailer this application can be pointed at actually resolves"* is
     * the lint that keeps that claim honest (4428).
     *
     * ⛔ **THE SENTENCE THAT USED TO SIT IN THAT PARAGRAPH — *"it is the AWS SDK
     * transport and `aws/aws-sdk-php` is not a dependency, so selecting it fails
     * at resolution"* — IS FALSE SINCE 2026-08-25 (9516).** The package entered
     * `composer.lock` for the object store and `ses` resolves. ⛔ **It is kept
     * and dated rather than deleted because it is the evidence**: a sentence
     * whose truth is an inventory of `composer.lock` goes stale on a change in
     * another layer entirely, and **nothing here reddened** — the resolution
     * lint's subject set is *this constant*, so a block outside it contributes
     * nothing to any offender list.
     *
     * ✅ **WHAT ACTUALLY CONTAINS AN UNDECLARED MAILER, STATED AS A PROPERTY**
     * (8861): membership of this list is what {@see self::isKnown()} answers,
     * `MailQuota::ceiling()` returns null for anything it does not recognise,
     * and `PlatformMailer::deliverNow()` asks for the ceiling **before** it
     * builds a transport. So an undeclared mailer is refused with the row named,
     * whatever `composer.lock` holds — driven by `PlatformMailerTest`'s *"a
     * framework mailer this application does not declare is refused before its
     * transport is built"* over `ses`, `postmark`, `resend` and `sendmail`.
     * ⛔ **The lint above and that test are not duplicates**: one asks *does
     * everything on this list work*, the other asks *what happens to everything
     * that is not on it*, and only the second could have seen this.
     *
     * ⛔ **IT IS ALSO WHAT `DefaultsManifest` BUILDS THE PER-MAILER SENDING
     * CEILINGS FROM, WHICH IS WHY IT IS A CONSTANT AND NOT A LIST IN TWO
     * FILES** (4603). A mailer that appears here without a declared ceiling key
     * would send unmetered; one that has a key and cannot be selected would seed
     * a row nobody can reach. Deriving both from one list is what makes those
     * two impossible rather than merely unlikely.
     *
     * @var list<string>
     */
    public const array MAILERS = ['smtp', 'gmail', 'log', 'array'];

    /**
     * Whether this is a mailer this application declares anything about.
     *
     * ⚠️ **THE ANSWER FOR ANYTHING ELSE IS "NO", NOT "PROBABLY FINE".** An
     * operator who sets `MAIL_MAILER=postmark` has selected a transport with no
     * declared ceiling and no declared feedback signal, and the fail-closed
     * reading of that is that we know nothing about it — see
     * {@see MailQuota::ceiling()}, which refuses rather than borrowing another
     * transport's limit.
     */
    public function isKnown(?string $mailer = null): bool
    {
        return in_array($mailer ?? $this->active(), self::MAILERS, true);
    }

    /**
     * The mailer currently carrying platform mail.
     */
    public function active(): string
    {
        return (string) config('mail.default');
    }

    /**
     * What the active mailer can tell us about a message after it leaves.
     */
    public function feedbackSignal(?string $mailer = null): MailFeedbackSignal
    {
        $mailer ??= $this->active();

        $configured = config("platform_mail.feedback.{$mailer}");

        if (! is_string($configured)) {
            return MailFeedbackSignal::None;
        }

        // ⚠️ `tryFrom`, and a misspelling lands on `None` rather than throwing.
        // A typo in `.env` must not take the sign-in mail down with it — it
        // should stop customer mail and leave account mail running, which is
        // exactly what the fail-closed value does.
        $signal = MailFeedbackSignal::tryFrom($configured) ?? MailFeedbackSignal::None;

        // ⛔ **THE CLAIM IS CHECKED, NOT TAKEN** — see the class docblock. A
        // `typed` declaration with no SNS topic allowlist behind it names a
        // feed that refuses every genuine event, so it degrades to the value
        // that refuses customer mail rather than the one that permits it.
        // Deliberately NOT applied to `ndr_only` or `none`: neither permits
        // anything, so neither has anything to downgrade.
        if ($signal === MailFeedbackSignal::Typed && ! $this->typedFeedIsSubscribed()) {
            return MailFeedbackSignal::None;
        }

        return $signal;
    }

    /**
     * Whether the typed feed has an SNS topic behind it.
     *
     * ⚠️ **THE SAME PREDICATE `SnsMessageVerifier::topicIsAllowed()` OPENS
     * WITH, ASKED FROM THE SENDING SIDE.** Not extracted into a shared helper
     * on purpose: there it is a *refusal* on an inbound request and here it is
     * a *precondition* on an outbound one, and collapsing them would put the
     * webhook's authentication behind a method the send path can be tempted to
     * relax. What matters is that they read the same key, and a lint could not
     * usefully assert more than that.
     */
    private function typedFeedIsSubscribed(): bool
    {
        $topics = config('platform_mail.sns.topic_arns');

        return is_array($topics) && $topics !== [];
    }

    /**
     * The account the sending ceiling belongs to.
     *
     * ⚠️ **NOT THE FROM ADDRESS.** Google's 2,000-a-day limit is charged to the
     * Workspace *user* the API call authenticates as, which is `GMAIL_SEND_AS`
     * and need not be what the message says it is from — an internal app can
     * send as one user with a different `From:` on every message. Metering the
     * from address would count one ceiling as several and never trip.
     *
     * Falls back to the configured from address for every other mailer, where
     * the sending identity genuinely is the from address.
     */
    public function sendingAccount(?string $mailer = null): string
    {
        $mailer ??= $this->active();

        if ($mailer === 'gmail') {
            $user = config('platform_mail.gmail.user');

            return is_string($user) && trim($user) !== '' ? trim($user) : 'me';
        }

        $from = config('mail.from.address');

        return is_string($from) && trim($from) !== '' ? trim($from) : $mailer;
    }
}
