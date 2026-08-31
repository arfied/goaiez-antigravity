<?php

declare(strict_types=1);

namespace App\Services\Messaging\Outbound;

use App\Contracts\SendDriver;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Services\Consent\SendPermit;
use InvalidArgumentException;

/**
 * One finished message, addressed, authorised, and keyed — the unit every
 * channel driver takes.
 *
 * This is the *shared* half of T137 §2's day-0 send-driver contract: **SMS and
 * email share the permit and the suppression path**, so they share the object
 * that carries the permit. What they do not share is the transport, which is
 * {@see SendDriver}.
 *
 * ## The permit is a property, not a parameter beside it
 *
 * 285 and 1566 put the permit in `PlatformTexter::sendToCustomer()`'s signature
 * so a caller who has not been through the consent gate cannot construct the
 * call. Widening that to two channels by adding a second `send($permit, $body)`
 * on a mailer would be the same rule written twice, and the second copy is the
 * one that drifts. Instead the permit rides *inside* this object and the object
 * has a private constructor: **there is no way to build an `OutboundMessage`
 * without a `SendPermit`, and only `ConsentService` mints those.**
 *
 * ⚠️ **THE CHANNEL IS READ OFF THE PERMIT AND IS NEVER A PARAMETER.** Consent is
 * per `(customer, channel)` and the Do Not Call, litigator and mini-TCPA
 * registers answer differently for each. A message that could name a channel
 * other than the one its permit was minted for is the exact mistake
 * `PlatformTexter` throws a `LogicException` about today, and the cheapest fix
 * is to make it unsayable rather than checked.
 *
 * ⚠️ **THE RECIPIENT IS THE PERMIT'S IDENTIFIER AND IS NEVER RE-DERIVED.** It is
 * the exact string suppression, the registers and the reassigned-numbers check
 * were asked about. `to()` reads it off the permit; there is no setter.
 *
 * ## What is deliberately absent
 *
 * **No templating, no truncation, no segmentation, no link shortening.** The
 * body arrives finished, for the SMS transport contract's reason: a helpful
 * `substr($body, 0, 159)` here would be the composer, built by accident, in the
 * layer least able to say what a message may say. `SL-2`'s ≤159 composer and
 * `SL-5`'s short-link service are L3's and their own lane's; both run *before*
 * this object exists.
 *
 * ⚠️ **THAT TRANSPORT CONTRACT IS DELIBERATELY NOT NAMED WITH A `{@see}` ABOVE,
 * AND THE OMISSION IS LOAD-BEARING.** `App\Contracts\Texter` is held to five
 * files by the messaging lint so nothing can reach a carrier without
 * `PlatformTexter`'s permit gate — and **Pint promotes a `{@see}` into a real
 * `use` statement**, which is indistinguishable from an import that intends to
 * call something. It did exactly that to this file on the first `composer lint`
 * of this slice, which is the second time that has happened here; `SentText`
 * carries the same warning for the same reason.
 *
 * **No schedule.** Whether this send may happen *now* — recipient-local quiet
 * hours for marketing, the rate governor, the per-tenant pause and the global
 * halt — is decided by the sender, before a driver is reached. An object
 * carrying a `sendAt` would invite a driver to hold it, and a transport that
 * queues is a transport that has learned something decision-shaped.
 *
 * ## Media, and why the list is URLs rather than bytes
 *
 * MMS on Infobip is submitted with externally fetchable media, and R8 confirms
 * MMS on the campaign. The list holds URLs this application serves; it never
 * holds binary, because a value object that can carry a megabyte ends up in a
 * queue payload, a log line and a failed-job row.
 *
 * ⚠️ **MEDIA ON A CHANNEL THAT CANNOT CARRY IT IS A PROGRAMMING ERROR, NOT A
 * DEGRADATION.** An email driver silently dropping the personalised picture
 * `SL-2` composed would send a message that means something different from the
 * one the campaign wrote, and nothing would say so. It throws.
 */
final readonly class OutboundMessage
{
    /**
     * @param  list<string>  $mediaUrls
     */
    private function __construct(
        public SendPermit $permit,
        public string $body,
        public ?string $subject,
        public array $mediaUrls,
        public OutreachPurpose $purpose,
        public SendKey $key,
    ) {}

    /**
     * The only way one of these comes into existence.
     *
     * @param  string  $body  Finished text. Not a template, not truncated here.
     * @param  string|null  $subject  Email only. ⚠️ **A subject on an SMS is not
     *                                ignored, it throws** — a composer that set
     *                                one believed it was writing an email, and a
     *                                driver silently dropping it would send a
     *                                message missing the half the author thought
     *                                mattered.
     * @param  array<array-key, string>  $mediaUrls  MMS attachments, as URLs
     *                                               this application serves. Empty for a
     *                                               plain SMS. ⚠️ **Declared as an array
     *                                               rather than a `list`, deliberately**: a
     *                                               caller filtering a collection hands over
     *                                               `[1 => '…', 3 => '…']` with no
     *                                               complaint from PHP, and the `list`
     *                                               annotation would be a promise the type
     *                                               system never checks. The property is a
     *                                               real list because `array_values()` below
     *                                               makes it one.
     *
     * @throws InvalidArgumentException on an empty body, or a field the
     *                                  permit's channel cannot carry
     */
    public static function for(
        SendPermit $permit,
        string $body,
        SendKey $key,
        OutreachPurpose $purpose = OutreachPurpose::Marketing,
        ?string $subject = null,
        array $mediaUrls = [],
    ): self {
        if (trim($body) === '') {
            throw new InvalidArgumentException(
                'A message with no body is not a message. An empty SMS still costs a segment, still '
                .'arrives, and still counts against the brand throughput — the recipient just cannot '
                .'tell what it was for.'
            );
        }

        if ($subject !== null && $permit->channel !== OutreachChannel::Email) {
            throw new InvalidArgumentException(
                "A subject line has no meaning on {$permit->channel->value}. A composer that set one "
                .'believed it was writing an email.'
            );
        }

        if ($mediaUrls !== [] && $permit->channel !== OutreachChannel::Sms) {
            throw new InvalidArgumentException(
                "Media cannot be carried on {$permit->channel->value} by this contract. MMS rides the "
                .'SMS channel on the same number (T137 R8); an email attachment is a different '
                .'mechanism and is not this one.'
            );
        }

        foreach ($mediaUrls as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new InvalidArgumentException(
                    'Every media item must be a URL a carrier can fetch. This contract never carries '
                    .'binary: a value object that can hold a megabyte ends up in a queue payload, a '
                    .'log line and a failed-job row.'
                );
            }
        }

        return new self(
            permit: $permit,
            body: $body,
            subject: $subject,
            mediaUrls: array_values($mediaUrls),
            purpose: $purpose,
            key: $key,
        );
    }

    /**
     * The channel this message travels on. Always the permit's.
     */
    public function channel(): OutreachChannel
    {
        return $this->permit->channel;
    }

    /**
     * The address this message goes to. Always the permit's identifier.
     */
    public function to(): string
    {
        return $this->permit->identifier;
    }

    /**
     * Whether this send carries media, and is therefore an MMS.
     *
     * ⛔ **THIS DOCBLOCK SAID "THIS DOES NOT DECIDE THE PRICE" AND IT NOW DOES —
     * REVERSED BY THE OWNER AT 9182, CONFIRMED AT 9193.** The old sentence read:
     * *"R9 charges one credit for an SMS, an MMS, or the SMS+MMS pair sent
     * together — so retail does not turn on this at all."* R9's retail clause is
     * overridden: **the text is a credit, the media is a credit, a message
     * carrying both is two.** So this answer now decides a charge as well as an
     * endpoint, and both books turn on it.
     *
     * ⛔ **AND SINCE 2026-08-30 IT NO LONGER DECIDES THE CHARGE — A COUNT DOES**
     * (12461). The owner ruled that **each** photo is a credit, so
     * `PlatformMessageSender::claim()` writes `count($message->mediaUrls)` to
     * `outreach_messages.media_count` and this predicate answers the *endpoint*
     * question only: whether this send is an MMS. ⚠️ **The two answers agree on
     * every send this application can compose today** — `RunCampaignJob` is the
     * only composer of media and composes at most one item — **which is exactly
     * why the reader had to move before that stops being true**, not after.
     *
     * ⚠️ **THE RETAIL READER IS THE PERSISTED ROW AND NOT THIS METHOD**, which
     * is 2548's rule and is why the two are not the same call.
     *
     * ⚠️ **A MEDIA-ONLY SEND CANNOT BE BUILT**, and that is what keeps the
     * retail floor unreachable rather than load-bearing: the constructor is
     * private, `for()` is the only mint, and it throws on an empty body — pinned
     * by `tests/Feature/SendContractTest.php`'s *"an outbound message refuses an
     * empty body"*. So `ceil(characters / 160)` is at least one on every send
     * this class can produce, and `SmsCreditUnits::forSend()`'s `max(1, …)`
     * never decides a real charge. ⛔ **If that refusal is ever lifted, the
     * floor stops being decoration on the same day.**
     */
    public function isMultimedia(): bool
    {
        return $this->mediaUrls !== [];
    }
}
