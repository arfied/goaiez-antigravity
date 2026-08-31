<?php

declare(strict_types=1);

namespace App\Services\Messaging\Outbound;

use App\Contracts\MessageSender;
use App\Contracts\SendDriver;
use App\Enums\OutreachChannel;
use App\Enums\SendRefusalReason;
use App\Exceptions\TextNotDeliverable;
use App\Services\Sms\PlatformTexter;
use LogicException;

/**
 * The SMS and MMS wire, reached the only way a wire may be reached
 * (decision 2540).
 *
 * `SendDriver`'s middle layer: {@see MessageSender} decided
 * *whether*, this decides *how on this channel*, and the transport underneath
 * decides nothing at all.
 *
 * ⛔ **THIS CLASS DELEGATES TO {@see PlatformTexter} AND MUST NEVER CALL A
 * TRANSPORT** (2170). `App\Contracts\Texter` and its two implementations are
 * held to five files by the lint in `tests/Feature/Architecture/MessagingTest.php`
 * — and this file is deliberately not one of them, because that lint is the only
 * thing standing between a caller and a carrier reached without the permit gate.
 * Adding this file to that allowlist would make it the sixth way around 1566,
 * and it would read as housekeeping.
 *
 * ## What this class does not do, and where each of those actually happens
 *
 * - **No consent, suppression or opt-out lookup.** It happened in
 *   `ConsentService`, which is the only thing that can mint the `SendPermit`
 *   riding inside the `OutboundMessage` this method takes. A second check that
 *   disagreed would be worse than none.
 * - **No branch on the sending basis.** 2098's `ImportAttestation` and
 *   platform-captured consent both arrive here as a permit and are
 *   indistinguishable on purpose — `messaging_lane` stays derived, and a driver
 *   that read `$message->permit->capturedBy` would be the second implementation
 *   of a rule that has exactly one.
 * - **No number selection.** `PlatformTexter` asks `NumberSelector`; a lint
 *   stops a *transport* naming that class, and this driver does not name it
 *   either, for the same reason one layer up: which number a message leaves on
 *   is a decision, and decisions do not belong under the permit.
 * - **No retry, no queueing, no holding.** Backoff belongs to the job that
 *   called the sender. A driver that retried would send twice on a timeout,
 *   because the vendor's first attempt may well have succeeded.
 * - **No credit debit and no `outreach_messages` row.** Both are the sender's,
 *   inside one transaction, around this call.
 *
 * ## MMS is this driver, not a second one
 *
 * ⚠️ **THE MEDIA RIDES THE SAME CALL, THE SAME NUMBER AND THE SAME PERMIT**
 * (2544, and 2193's "voice, SMS and MMS ride one number with no capability
 * flag"). An SMS, an MMS and the SMS+MMS pair are each **one** send, so a
 * separate `MmsSendDriver` would be a second `SendDriver` for
 * one message — and something would then have to choose between them, which is
 * the routing decision this layer exists not to contain. `InfobipClient` picks
 * `/mms/2/messages` over `/sms/3/messages` from whether the list is empty, and
 * nothing above the transport knows there were two endpoints.
 *
 * ⚠️ **THIS PARAGRAPH USED TO ARGUE FROM THE PRICE — "R9 prices an SMS, an MMS,
 * or the SMS+MMS pair sent together as **one** send" — AND 9182 REVERSED THE
 * PRICE.** A message carrying both is **two credits**. The argument for one
 * driver never rested on the price and does not now: it rests on it being one
 * message on one number under one permit. **Nothing on this path debits
 * anything** — the debit is `SendCredits`', one layer up, before this is
 * reached.
 */
final class SmsSendDriver implements SendDriver
{
    public function __construct(
        private readonly PlatformTexter $texter,
    ) {}

    public function channel(): OutreachChannel
    {
        return OutreachChannel::Sms;
    }

    /**
     * Put this message on the wire, or say why this channel could not.
     *
     * ⚠️ **THE CHANNEL IS CHECKED EVEN THOUGH THE SENDER ALREADY ROUTED BY IT,
     * AND THIS IS NOT 398's UNFALSIFIABLE INNER GUARD.** The outer routing picks
     * a driver from a map, so a wrong entry in that map — an email
     * `OutboundMessage` reaching this class — is exactly the mistake the outer
     * layer cannot catch, because it is the outer layer making it. It throws
     * rather than refusing: a permit for another channel authorises nothing
     * here, `PlatformTexter` throws on the same mistake, and a *refusal* would
     * record a compliance-shaped answer to what is a programming error.
     *
     * @throws LogicException when handed a message for another channel
     * @throws TextNotDeliverable when the carrier could not be reached or would
     *                            not take it. Never returned as a value.
     */
    public function deliver(OutboundMessage $message): SendOutcome
    {
        if ($message->channel() !== OutreachChannel::Sms) {
            throw new LogicException(
                "The SMS driver was handed a {$message->channel()->value} message. Consent, the Do "
                .'Not Call registers and the mini-TCPA windows all answer per channel, so a message '
                .'routed to the wrong wire was authorised for a different one.'
            );
        }

        $sent = $this->texter->sendToCustomer($message->permit, $message->body, $message->mediaUrls);

        if ($sent === null) {
            // ⚠️ **A REFUSAL, NOT A FAILURE, AND THE SENDER ROLLS ITS ROW AND
            // ITS DEBIT BACK ON IT.** `PlatformTexter` answers null for the
            // `sms.enabled` kill switch and for an inventory where nothing may
            // send, and collapses the two deliberately. Committing a row here
            // would record a message that was never sent and never will be —
            // and a credit would have been taken for it.
            return SendOutcome::refused($message->key, SendRefusalReason::ChannelUnavailable);
        }

        return SendOutcome::accepted(
            key: $message->key,
            providerMessageId: $sent->providerMessageId,
            // Filled from the selection `PlatformTexter` made, never re-derived
            // from what the carrier echoed back. Null is the bootstrap case: no
            // inventory, so the transport used its configured sender and there
            // is no row to name.
            numberId: $sent->numberId,
            // The vendor's own word, verbatim and never shown to an owner —
            // `MessageLog`'s rule.
            vendorStatus: $sent->vendorStatus,
        );
    }
}
