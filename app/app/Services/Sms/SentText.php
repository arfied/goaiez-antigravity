<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * What the carrier said when it took the message.
 *
 * ⚠️ **THIS IS AN ACKNOWLEDGEMENT OF RECEIPT, NOT A DELIVERY.** Infobip answers
 * a successful submission with `status.groupName` of `PENDING` and
 * `status.name` of `PENDING_ACCEPTED` — the message is in their queue, and
 * whether a handset ever saw it is a *later, separate* fact that arrives on the
 * delivery-receipt webhook (slice 3). Reading this object as proof of delivery
 * is decision 620's inversion waiting to happen: `outreach_messages` would show
 * `Delivered` for every message the carrier merely agreed to think about.
 *
 * That is why there is no `delivered` flag and no `OutreachStatus` on this
 * class. The only status transition slice 1 can honestly justify is
 * `Queued → Sent`, and even that belongs to the caller that owns the row.
 *
 * WHY `providerMessageId` IS NOT NULLABLE. It is the only handle a delivery
 * receipt can be matched back on, so a submission we cannot name is a message
 * we can never resolve — slice 3 would file its receipt against nothing and the
 * row would sit `Queued` forever, which is the exact failure §2.10.3 row 3 says
 * the receipt webhook exists to prevent. {@see InfobipClient} therefore refuses
 * a 200 it cannot read an id out of rather than inventing one.
 *
 * The transport contract that returns this object is not named here — see the
 * note under the class for why that is a lint requirement rather than an
 * omission.
 */
final readonly class SentText
{
    public function __construct(
        /** The carrier's handle for this one message. Slice 3's join key. */
        public string $providerMessageId,
        /**
         * The carrier's handle for the batch this message travelled in.
         *
         * Null from every driver that does not batch. Kept because Infobip's
         * own logs and status endpoints are queryable by bulk id and a support
         * conversation with them starts with it.
         */
        public ?string $bulkId = null,
        /**
         * The vendor's own word for what it did, verbatim — `PENDING_ACCEPTED`
         * and its siblings.
         *
         * ⚠️ **Never shown to an owner and never mapped here.** `MessageLog`'s
         * own rule: the stored vendor string *"is written for us, and putting it
         * in front of an owner turns a delivery problem into a support ticket
         * about a message they cannot read."* Translating it into
         * `OutreachStatus` is slice 3's job, done once, against the receipt
         * webhook's vocabulary rather than the submission response's.
         */
        public ?string $vendorStatus = null,
        /**
         * Which `phone_numbers` row this went out on.
         *
         * ⚠️ **NO DRIVER FILLS THIS IN AND NO DRIVER COULD.** A transport is
         * handed an E.164 string and knows nothing about the inventory it came
         * from — deliberately, because a transport that could resolve a number
         * to a row is a transport one commit away from choosing one.
         * `PlatformTexter` fills it through {@see self::withNumber()} after the
         * carrier answers, from the selection it had already made.
         *
         * ⚠️ **IT RIDES HERE RATHER THAN ON A NEW RETURN TYPE**, because
         * `ReviewInviteSender` is the one caller and it already reads
         * `providerMessageId` off this same value to write
         * `outreach_messages.provider_msg_id`. A second object would be a second
         * thing to thread through the same two call sites for one integer.
         *
         * Null when the platform has no number inventory yet — the bootstrap
         * case, where `InfobipClient` used its configured sender and there is no
         * row to name.
         */
        public ?int $numberId = null,
    ) {}

    /**
     * The same acknowledgement, naming the number it went out on.
     *
     * A new instance rather than a mutation: this class is `readonly` precisely
     * so what the carrier said cannot be edited after the fact, and an exception
     * for one column would be an exception for all of them.
     */
    public function withNumber(?int $numberId): self
    {
        return new self(
            providerMessageId: $this->providerMessageId,
            bulkId: $this->bulkId,
            vendorStatus: $this->vendorStatus,
            numberId: $numberId,
        );
    }
}
// ⚠️ THIS FILE DELIBERATELY DOES NOT NAME THE TRANSPORT CONTRACT, not even in a
// docblock `@see`. The messaging lint holds `App\Contracts\Texter` to five
// files so that nothing can ask a driver to send without going through
// `PlatformTexter` — and Pint promotes a `{@see}` into a real `use` statement,
// which is indistinguishable from an import that intends to call something. It
// caught this file on the lint's first run. A value object has no business
// naming the interface that returns it either way.
