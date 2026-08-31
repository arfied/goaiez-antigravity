<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when this application is not in a position to actually deliver email.
 *
 * ⚠️ **THE FAILURE THIS EXISTS TO CONVERT IS A SUCCESS.** Laravel's `log` mailer
 * — the value `.env.example` has shipped since FOUND-01 and the framework's own
 * default — accepts every message, writes it to `storage/logs`, and returns
 * normally. So does `array`. Nothing throws, nothing warns, no queue job fails,
 * and every test passes. The only evidence that a person did not receive their
 * email is that they say so, and for the one message this application sends
 * today — a sign-in link — the person who would say so is the one who cannot
 * sign in to tell us.
 *
 * That is why the guard is an exception rather than a log line. A log line about
 * a mailer that is writing to the log is a joke at the operator's expense.
 *
 * ⚠️ **IT IS RAISED ON THE QUEUE, NEVER ON THE REQUEST.** See
 * `App\Jobs\DeliverPlatformMail` for why that is a correctness requirement and
 * not a preference: raising it synchronously inside `MagicLinkService::request()`
 * would break the login page *and* turn the mailer's configuration into an
 * account-enumeration oracle, because that path only reaches a send when the
 * address belongs to somebody.
 *
 * The message is written for whoever is reading `failed_jobs` at the time,
 * which is an operator and never a customer.
 */
final class MailNotDeliverable extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function transport(string $mailer): self
    {
        return new self(
            "The '{$mailer}' mailer does not deliver anything — it accepts the message and drops it. "
            .'Set MAIL_MAILER to a real transport and re-run `composer deploy`, then release this job.'
        );
    }

    public static function noFromAddress(): self
    {
        return new self(
            'No from address is configured, so there is nothing to send as. Set MAIL_FROM_ADDRESS.'
        );
    }

    public static function placeholderFromAddress(string $address): self
    {
        return new self(
            "The from address is still the framework's placeholder ({$address}). "
            .'Set MAIL_FROM_ADDRESS to a real address on a sending subdomain.'
        );
    }

    /**
     * ⚠️ **THE EXAMPLE IN THIS MESSAGE HAS MOVED TWICE AND THE RULE HAS NOT.**
     * It named `reports.goaiez.com` until 2026-08-11, when 2114 superseded it
     * with `mail.goaiez.com`, and it names `goaieasy.net` from 2026-08-19,
     * when 5500 ruled that a subdomain of the primary domain is not separation
     * at all. Decision 30's load-bearing half — never the primary domain — is
     * what this method enforces and it is untouched by both reversals. The
     * domain is named here as an illustration; the enforced value is the
     * `mail.sending_domain` registry seed, checked by a separate guard so that
     * these two rules cannot be confused for one.
     *
     * ⚠️ **THE WORD IS "SENDING DOMAIN" RATHER THAN "SENDING SUBDOMAIN" NOW**,
     * and that is 5500 rather than tidying: an operator told to use "the
     * sending subdomain" would reach for one under the primary domain, which is
     * the arrangement the ruling refused.
     */
    public static function primaryDomainFromAddress(string $address, string $host): self
    {
        return new self(
            "Decision 30 forbids sending from the primary domain: {$address} is on {$host}, "
            .'which is the domain this application answers on. Use the platform sending domain '
            .'(goaieasy.net, decision 5500) so a deliverability problem cannot reach the '
            .'primary domain\'s reputation.'
        );
    }

    public static function wrongSendingDomain(string $address, string $host, string $expected): self
    {
        return new self(
            "The from address {$address} is on {$host}, and this platform sends only from "
            ."{$expected} (decision 5500, superseding 2114's mail.goaiez.com). SPF, "
            .'DKIM and DMARC are published for that domain and for no other, so mail from '
            .'anywhere else fails authentication at the receiving server. Change '
            .'MAIL_FROM_ADDRESS, or move the mail.sending_domain row in Ops if the domain '
            .'itself has changed.'
        );
    }

    /**
     * ⛔ **THE THIRD CEILING REFUSAL, AND IT IS THE ONE THAT FIRES BEFORE A
     * SINGLE MESSAGE HAS BEEN SENT** (4604). The ceiling is per mailer since
     * 4603, and the `smtp` mailer — which is how SES is reached — carries no
     * seed, because every figure this codebase could state for an arbitrary
     * relay is a false one. So a deployment that sets `MAIL_MAILER=smtp` and
     * nothing else sends nothing, and is told which row to set rather than
     * being held to Google's 2,000 against an account that will accept 200.
     *
     * The key is named because a refusal that cannot say what to set is a
     * refusal somebody has to come and ask about.
     */
    public static function ceilingNotStated(string $mailer, string $key): self
    {
        return new self(
            "No 24-hour sending ceiling has been stated for the '{$mailer}' mailer, so nothing "
            ."may be sent over it. Set {$key} in Ops to the sending account's own 24-hour quota "
            .'— for a fresh Amazon SES account that is 200 until AWS grants production access, '
            .'and whatever was granted afterwards. It is deliberately unseeded: this application '
            .'cannot know which relay this mailer points at, and a borrowed figure would read as '
            .'a real limit (decisions 4456, 4604).'
        );
    }

    /**
     * ⚠️ **TWO CEILING REFUSALS, AND THEY ARE NOT THE SAME EVENT.** This one is
     * the hard limit and stops every message including a sign-in link; its
     * sibling below stops customer mail early so that this one is never
     * reached. One message for both would tell an operator the platform is down
     * when it is deliberately holding a reserve.
     */
    public static function ceilingReached(int $used, int $ceiling): self
    {
        return new self(
            "The sending account has used {$used} of its {$ceiling} messages in the last 24 "
            .'hours, so nothing further may be sent. The window is rolling rather than daily: '
            .'it clears message by message as sends age out, and exceeding the limit at the '
            .'provider stops the account accepting mail for up to 24 hours. Raise this mailer\'s '
            .'own ceiling row in Ops only if the provider\'s own limit has actually changed — '
            .'the ceiling is per mailer, so raising it raises this transport\'s and no other\'s.'
        );
    }

    public static function customerCeilingReached(int $used, int $ceiling): self
    {
        return new self(
            "The sending account has used {$used} of its {$ceiling} messages in the last 24 "
            .'hours and the remainder is reserved for platform mail — a sign-in link must not '
            .'fail because a batch consumed the quota. Customer mail resumes as the rolling '
            .'window clears. The reserve is mail.ceiling_customer_reserve.'
        );
    }

    /**
     * Open question H, refused at the send rather than remembered in a document.
     */
    public static function noFeedbackSignal(string $mailer, string $reason): self
    {
        return new self(
            "The '{$mailer}' mailer may not be used to email somebody else's customer. {$reason}"
        );
    }

    /**
     * CAN-SPAM §7704(a)(5)(A)(iii), refused at the send — T176 P21.
     *
     * ⛔ **THIS IS WHERE "NO ADDRESS CONFIGURED" BECOMES "NO COMMERCIAL MAIL",
     * AND NOTHING ELSE ENFORCES IT.** `mail.postal_address` deliberately carries
     * no seed (decision 4019) because every possible seed for an address is a
     * false statement — so the registry answers `null`, which on its own is a
     * value somebody could shrug at. It covers the operator who set the row to
     * whitespace on the same terms: a row that reads back as "set" and says
     * nothing would satisfy every check we have and satisfy the statute not at
     * all.
     */
    public static function noPostalAddress(): self
    {
        return new self(
            'A commercial email must carry a valid physical postal address of the sender '
            .'(CAN-SPAM §7704(a)(5)(A)(iii)) and mail.postal_address is empty. The send is '
            .'refused rather than going out with the line missing or invented. Set the row in '
            .'Ops — it is under "Set by an operator only", and decision 4019 records why it '
            .'ships with no default.'
        );
    }

    /**
     * A commercial message with no way for the recipient to leave — T176 P21.
     *
     * ⛔ **THIS IS THE ACCOUNT-HOLDER PATH REFUSING A COMMERCIAL NOTIFICATION,
     * AND IT IS NOT A GAP TO BE FILLED IN WHOEVER'S NEXT SLICE.** The only
     * suppression store this application has is `ConsentService`'s, keyed on a
     * tenant's **customer**; there is no equivalent for a `User`. So an
     * unsubscribe link on a message to an account holder would be a control that
     * does nothing, offered under a statute that requires it to work. Building
     * the store is the answer; sending the message without one is not.
     */
    public static function commercialWithoutOptOut(): self
    {
        return new self(
            'This notification declares itself commercial under CAN-SPAM, and the send carries '
            .'no tenant to attribute an opt-out to — which is the account-holder path, where no '
            .'suppression store exists. A commercial message must carry a working opt-out '
            .'(§7704(a)(3)), so it is refused rather than sent with one that writes nowhere.'
        );
    }

    /**
     * A notification that has not said which CAN-SPAM class it is — T176 P21.
     *
     * ⚠️ **FAIL CLOSED RATHER THAN ASSUME TRANSACTIONAL.** Assuming is the
     * cheaper-looking answer and it is the wrong direction: it would let a new
     * marketing notification go out with no footer and no opt-out, silently,
     * and the omission would look exactly like a decision. The lint in
     * `tests/Feature/Architecture/MailTest.php` catches this at build time; this
     * is what catches it if the lint is ever narrowed.
     */
    public static function unclassifiedNotification(string $notification): self
    {
        return new self(
            "{$notification} does not implement App\\Contracts\\Mail\\ClassifiesUnderCanSpam, so "
            .'nothing knows whether it owes the recipient an opt-out and a postal address. Declare '
            .'canSpamClass() on it — the argument goes in that class\'s docblock, not in a registry.'
        );
    }
}
