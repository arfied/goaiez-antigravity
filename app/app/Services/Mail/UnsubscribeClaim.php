<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Enums\OutreachChannel;
use Carbon\CarbonImmutable;

/**
 * What an unsubscribe token turns out to have been carrying — T176 P21.
 *
 * ⚠️ **IT NAMES THE ADDRESS THE MESSAGE WAS SENT TO, NOT THE ONE ON THE CONTACT
 * ROW TODAY.** That is `PlatformMailer::sendToCustomer()`'s rule about
 * `$permit->identifier`, applied to the other end of the same message: consent
 * was decided against one string, the mail was delivered to that string, and the
 * person clicking unsubscribe is refusing *that* address. Re-reading
 * `$customer->email` at unsubscribe time would suppress whatever the row says
 * now — a second address, or one edited in between — and would leave the
 * address that actually received the mail perfectly sendable.
 *
 * ⚠️ **NO CUSTOMER ID, DELIBERATELY.** `ConsentService::suppress()` is keyed on
 * the identifier and the channel and needs nothing else, so carrying a contact
 * id would put a row reference in a URL that travels through every relay between
 * here and the recipient's mailbox in exchange for nothing.
 */
final readonly class UnsubscribeClaim
{
    public function __construct(
        /**
         * The tenant whose message this was. The token is what establishes it —
         * the request arrives from a mail client with no session and no host to
         * resolve one from, which is `ShortLinkController`'s situation exactly.
         */
        public int $businessId,
        /**
         * The address the message was delivered to, verbatim.
         */
        public string $identifier,
        public OutreachChannel $channel,
        public CarbonImmutable $issuedAt,
    ) {}
}
