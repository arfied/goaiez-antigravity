<?php

declare(strict_types=1);

namespace App\Services\Messaging\Outbound;

use App\Enums\OutreachChannel;
use App\Services\Consent\SendPermit;
use App\Support\Identifier;
use App\Support\Tenancy;
use InvalidArgumentException;

/**
 * The idempotency key for exactly one send.
 *
 * T137 §3.1: *"one key per send/charge; retries and double-clicks can never
 * duplicate a message or a debit; credit debit is transactional with the send."*
 * That sentence has two halves and this type is what makes them the same half —
 * the message row, the vendor call and the credit debit all key on this one
 * string, so there is no arrangement of retries in which one of the three
 * happens twice and the others once.
 *
 * ## Why it is derived rather than random
 *
 * A `Str::uuid()` minted at the call site is unique per *attempt*, which is the
 * opposite of what idempotency needs: a job retried after a timeout would mint a
 * second uuid and send a second message. The key has to be a function of *what
 * is being sent to whom, on whose behalf, on what occasion* — so the retry
 * computes the same key and the second send is refused by a unique index rather
 * than by somebody remembering to check.
 *
 * ⚠️ **AND IT MUST BE COMPUTED FROM THE PERMIT, NOT FROM A MODEL.** The permit
 * carries the identifier that suppression, the Do Not Call registers and the
 * mini-TCPA windows were all asked about ({@see SendPermit}). A key computed
 * from `$customer->phone` can differ from the number actually sent to — which
 * would make the deduplication key describe a different message from the one
 * that went out, and the dedupe would silently stop deduplicating.
 *
 * ## Why the identifier is hashed into it and never stored raw
 *
 * This value reaches a unique index, a log line, a job payload and a Horizon
 * tag. `29` §2 forbids storing a raw IP and this codebase treats a phone number
 * with the same care — {@see Identifier::hash()} already exists for exactly this
 * and is the one hashing function consent, suppression and the registers agree
 * on. A key holding `+15555550123` in clear would put a mobile number in every
 * one of those places at once.
 *
 * ## The occasion is the caller's, and it is required
 *
 * `campaign:17:step:2`, `missed_call:9931`, `review_invite:8812`. It is what
 * makes two *legitimately different* messages to the same person on the same day
 * two different sends. There is deliberately no default: a caller who does not
 * supply one would silently collapse every message it ever sends to a contact
 * into one key, and the second campaign would be dropped as a duplicate with no
 * error anywhere. L3 and L5 both own occasions; neither may pass a constant.
 *
 * ⚠️ **THE BODY IS NOT IN THE KEY, DELIBERATELY.** A composer that renders a
 * name slightly differently on the retry — a normaliser change, a timestamp, a
 * short link minted per attempt — would produce a different key and send twice.
 * Idempotency keys on the *decision to send*, never on the rendered text.
 */
final readonly class SendKey
{
    /**
     * @param  string  $value  Opaque, deterministic, free of personal data.
     */
    private function __construct(public string $value) {}

    /**
     * Derive the key for one send.
     *
     * @param  string  $occasion  What this send *is*, in the caller's own terms.
     *                            Required, and never a constant — see the class
     *                            docblock for what a constant does here.
     *
     * @throws InvalidArgumentException when the occasion is empty, or the
     *                                  permit's identifier cannot be hashed —
     *                                  the second is not a formatting complaint,
     *                                  it means the thing being sent to is not a
     *                                  usable address and the send is about to
     *                                  fail anyway.
     */
    public static function for(SendPermit $permit, string $occasion): self
    {
        $occasion = trim($occasion);

        if ($occasion === '') {
            throw new InvalidArgumentException(
                'A send key needs the occasion of the send. Without one every message this caller '
                .'ever sends to a contact collapses into a single key and the second is dropped as a '
                .'duplicate, silently.'
            );
        }

        $hashed = Identifier::hash($permit->identifier, $permit->channel);

        if ($hashed === null) {
            throw new InvalidArgumentException(
                'A send key cannot be derived for an identifier that does not normalise. '
                .'The permit named an address no gate downstream could act on.'
            );
        }

        return new self(hash('sha256', implode('|', [
            // The tenant is in the key because the same occasion string means
            // different things in different tenants, and because a key that
            // collides across tenants would let one tenant's send suppress
            // another's.
            (string) Tenancy::idOrFail(),
            $permit->channel->value,
            $hashed,
            $occasion,
        ])));
    }

    /**
     * The key for a send on a channel this application originates itself, with
     * no customer and therefore no permit.
     *
     * ⚠️ **THERE IS EXACTLY ONE SUCH CATEGORY AND IT IS NOT A CUSTOMER SEND.**
     * R7's owner notification — the voicemail and its transcript delivered to
     * the business owner — goes to the account holder on the account
     * relationship, which is `PlatformMailer`'s existing authorisation model and
     * is why that class has a `send()` and `PlatformTexter` deliberately does
     * not. This factory exists so that path can still be idempotent; it does
     * **not** exist so a caller with no permit can reach a customer.
     *
     * @param  string  $recipient  The account holder's address. Hashed, never
     *                             stored here in clear.
     */
    public static function forAccountHolder(OutreachChannel $channel, string $recipient, string $occasion): self
    {
        $occasion = trim($occasion);

        if ($occasion === '') {
            throw new InvalidArgumentException('A send key needs the occasion of the send.');
        }

        $hashed = Identifier::hash($recipient, $channel);

        if ($hashed === null) {
            throw new InvalidArgumentException(
                'A send key cannot be derived for an identifier that does not normalise.'
            );
        }

        return new self(hash('sha256', implode('|', [
            (string) Tenancy::idOrFail(),
            $channel->value,
            $hashed,
            'account-holder',
            $occasion,
        ])));
    }
}
