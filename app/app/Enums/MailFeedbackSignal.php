<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a mail transport can tell us about a message after it left.
 *
 * ⚠️ **THIS ENUM IS OPEN QUESTION H MADE INTO A TYPE, AND THAT IS THE WHOLE
 * POINT OF IT.** H — bounce and complaint handling — has been *"blocks the
 * first customer-facing send"* since Stage 0, and it has been reopened and
 * re-closed four times by vendor changes, most recently by 2094: SES was
 * adopted partly *because* SNS gives a typed bounce and complaint event, and
 * then Workspace became primary (2093) on a transport that has neither. A note
 * in a document cannot survive that. A value the send path reads can.
 *
 * ⚠️ **THE THREE CASES ARE A CLAIM ABOUT WHAT WE CAN OBSERVE, NOT ABOUT
 * QUALITY.** A transport is not better for having a typed feed; it is *usable
 * for mail to somebody else's customer* for having one, and unusable for that
 * without one, because the only thing standing between a bad address list and a
 * dead sending domain is knowing which addresses are bad.
 */
enum MailFeedbackSignal: string
{
    /**
     * A typed, per-recipient bounce and complaint event, delivered to us.
     *
     * Amazon SES publishing to SNS is the one transport in this application
     * that has this: `eventType` of `Bounce` or `Complaint`, with
     * `bounce.bounceType` in `Permanent`/`Transient`/`Undetermined` and a
     * `bouncedRecipients[].emailAddress` or `complainedRecipients[].
     * emailAddress` naming exactly who (docs.aws.amazon.com, *Contents of event
     * data that Amazon SES publishes to Amazon SNS*, read 2026-08-11).
     *
     * 1193's argument for why this is not merely nicer: it is a typed event
     * rather than a heuristic over free-form NDR prose written by whichever
     * server rejected the mail.
     */
    case Typed = 'typed';

    /**
     * Bounces arrive as a delivery-status notification in a mailbox, and
     * complaints do not arrive at all.
     *
     * ⚠️ **THIS IS THE GOOGLE WORKSPACE CASE AND IT IS NOT HALF OF `Typed`.**
     * The bounce half is recoverable: a Workspace NDR carries an RFC 3464
     * `message/delivery-status` part with `Action:` and `Status:` fields, so
     * something machine-readable does exist — but it is delivered as an email
     * to the sending mailbox, which means reading a mailbox to find it, which
     * is precisely the arrangement 1192 records as a different product from
     * sending.
     *
     * ⛔ **THE COMPLAINT HALF IS NOT RECOVERABLE AT ALL.** Google runs no
     * per-recipient feedback loop for Workspace senders; Postmaster Tools
     * reports an aggregate domain spam rate and names nobody. So on this
     * transport there is no address that can ever be suppressed for having
     * reported us — the suppression simply cannot be written, and no amount of
     * building fixes it. That is the finding, and it is why this case does not
     * permit customer-facing mail either.
     */
    case NdrOnly = 'ndr_only';

    /**
     * Nothing comes back. The `log` and `array` transports, and a plain SMTP
     * relay with no feedback arrangement configured.
     *
     * ⚠️ **THE DEFAULT, AND DELIBERATELY SO.** An unrecognised mailer answers
     * `None`, which refuses customer-facing mail — 486's rule, that the
     * safe-looking default is usually the permissive one, applied to a map an
     * operator can extend in configuration.
     */
    case None = 'none';

    /**
     * Whether this application may email somebody else's customer over a
     * transport with this signal.
     *
     * ⚠️ **`Typed` ONLY, AND THE OTHER TWO ARE REFUSED RATHER THAN WARNED
     * ABOUT.** `CLAUDE.md` has said since Stage 0 that the first customer-facing
     * send is blocked on bounce handling; 2094 says the Workspace ruling *"makes
     * the blocker harder rather than removing it"* and that the cheapest fix is
     * *"to send through the SES driver the seam already carries"*. This method
     * is that sentence, enforced. Platform mail to an account holder — a sign-in
     * link, a support notice — is unaffected on every transport, because the
     * account relationship is the authorisation and a bounce there costs us a
     * login rather than a sending domain.
     */
    public function permitsCustomerMail(): bool
    {
        return match ($this) {
            self::Typed => true,
            self::NdrOnly, self::None => false,
        };
    }

    /**
     * Why customer-facing mail is refused on this signal, in words an operator
     * can act on.
     *
     * Two different refusals because two different things are wrong, and one
     * message for both would send somebody hunting for a feature in one case
     * and give up in the other — `SuppressionReason::liftRefusal()`'s reasoning,
     * one domain over.
     */
    public function customerMailRefusal(): ?string
    {
        return match ($this) {
            self::Typed => null,
            self::NdrOnly => 'This transport reports bounces only as delivery-status mail in a '
                .'mailbox and reports complaints not at all, so an address that reported us as '
                .'spam can never be suppressed. Send customer mail through a transport with a '
                .'typed bounce and complaint feed (open question H, decisions 2069 and 2094).',
            self::None => 'This transport reports nothing about a message after it leaves, so a '
                .'bad address cannot be suppressed and a complaint cannot be seen. Configure a '
                .'transport with a typed bounce and complaint feed before mailing anybody '
                .'else\'s customer (open question H).',
        };
    }
}
