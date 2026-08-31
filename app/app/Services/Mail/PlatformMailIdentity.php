<?php

declare(strict_types=1);

namespace App\Services\Mail;

/**
 * Who a customer-facing message says it is from, and where a reply goes.
 *
 * T137 R3: *"On-behalf-of = '{Business} via GOAIEZ' from-identity."*
 *
 * ⚠️ **THE DISPLAY NAME CHANGES AND THE ADDRESS DOES NOT.** The message is
 * `From: "Acme Dental via GO AI EZ" <invites@goaieasy.net>` — the tenant's
 * name in the part a human reads, our own domain in the part a mail server
 * authenticates. Putting the tenant's own domain in the address would break SPF
 * and DKIM alignment on every send and land the mail in spam, which is 1195's
 * failure exactly: invisible in testing, total in production.
 *
 * ⚠️ **AND THE "VIA" IS NOT DECORATION.** Lane A's disclosure rule — the one
 * `ReviewInviteSender::compose()` applies to a text message — is that a message
 * sent under platform-captured consent on platform infrastructure says so.
 * Gmail renders its own "via goaieasy.net" line when the domains differ, and
 * that is a fact about one mail client rather than a disclosure we control;
 * this is the one we control.
 *
 * ⚠️ **NULL IS A REAL STATE AND MEANS PLATFORM MAIL.** A sign-in link is from
 * GO AI EZ and from nobody else — attaching a business name to it would claim a
 * tenant sent somebody their own password reset.
 *
 * ⚠️ **THREE OF THE FOUR FIELDS TRAVEL IN THE QUEUE PAYLOAD AND THE FOURTH
 * NEVER DOES** (T176 P21). `DeliverPlatformMail` serialises whatever it is
 * handed, and `$footer` carries a sealed unsubscribe token whose plaintext is
 * the recipient's address — so it is built in `PlatformMailer::deliverNow()`,
 * after the queue hop, by {@see self::with()}. The instance the job holds always
 * has `$footer === null`, and `CanSpamMailTest` asserts it rather than leaving
 * it to this paragraph.
 */
final readonly class PlatformMailIdentity
{
    public function __construct(
        /**
         * The tenant this message is sent on behalf of, or null for platform
         * mail to an account holder.
         */
        public ?string $businessName = null,

        /**
         * The eight characters a reply comes back on (2097), or null when the
         * message is not one a reply should be threaded from.
         */
        public ?string $trackingCode = null,

        /**
         * The tenant an opt-out from this message would be attributed to, or
         * null for platform mail.
         *
         * ⚠️ **AN ID RATHER THAN THE `Business`, AND NOT THE CUSTOMER AT ALL.**
         * `ConsentService::suppress()` is keyed on the identifier and the
         * channel, so a contact id would buy nothing and would put a row
         * reference in a queue payload that outlives the row. The business id
         * is needed because `suppression_list` is tenant-owned and the
         * unsubscribe request arrives with no tenant of its own.
         */
        public ?int $businessId = null,

        /**
         * The CAN-SPAM block to append, or null when this message does not owe
         * one. ⛔ **Never set by a caller that queues** — see the class docblock.
         */
        public ?CanSpamFooter $footer = null,
    ) {}

    /**
     * The same identity with a footer attached, minted at delivery.
     */
    public function with(CanSpamFooter $footer): self
    {
        return new self($this->businessName, $this->trackingCode, $this->businessId, $footer);
    }

    /**
     * The `From:` display name for this message.
     *
     * ⚠️ **THE TENANT'S NAME IS SANITISED, AND THIS IS THE ONE PLACE IT COULD
     * DO DAMAGE.** A business name is tenant-supplied text going into a mail
     * header, so a newline in it is header injection — an extra `Bcc:` line
     * written by whoever typed the business name. Symfony's own header encoding
     * would refuse most of it; refusing here as well means the refusal happens
     * where it can be explained rather than as a mailer exception three layers
     * down. Control characters are removed rather than rejected, because the
     * alternative is a tenant whose mail silently stops because of a stray tab
     * in their trading name.
     */
    public function fromName(string $platformName): string
    {
        if ($this->businessName === null) {
            return $platformName;
        }

        $business = trim((string) preg_replace('/[\p{C}]+/u', ' ', $this->businessName));

        if ($business === '') {
            return $platformName;
        }

        return $business.' via '.$platformName;
    }
}
