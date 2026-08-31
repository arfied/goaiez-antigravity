<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\PhoneNumber;

/**
 * A number that may originate a send, and the proof that it may.
 *
 * ⚠️ **THIS IS `SendPermit`'s PATTERN ONE LAYER DOWN, AND THE TWO ANSWER
 * DIFFERENT QUESTIONS.** A `SendPermit` says *this person may be messaged*;
 * this says *this platform may originate traffic from this number*. Both have to
 * be true and neither is evidence about the other, which is exactly why the
 * second one is a type rather than an `if` — a call site holding a permit reads
 * as fully authorised, and the number half would be the check somebody forgets.
 *
 * ⚠️ **THE CONSTRUCTOR IS PRIVATE AND {@see self::from()} IS THE ONLY FACTORY,
 * SO I38 IS ENFORCED BY CONSTRUCTION RATHER THAN BY A CHECK SOMEBODY
 * REMEMBERS.** Doc 51's I38 — *no send from a number that is not in a sendable
 * state* — is one line of `if` in the obvious implementation, and one line of
 * `if` is one line somebody deletes while debugging a quarantine that fired in
 * staging. Here there is nothing to delete: a quarantined, provisioning,
 * retired or released row **cannot be turned into this object**, so the send
 * path cannot express the message at all. Decision 220's rule and 1566's, in the
 * layer under both.
 *
 * An `ArchitectureTest` lint confines callers of `SendingNumber::from` to
 * {@see NumberSelector}, for the reason the consent service's own factory lint
 * exists: a factory anybody may call is a private constructor with extra steps.
 *
 * ⚠️ **IT CARRIES THE ROW ID AS WELL AS THE NUMBER, AND THE ID IS NOT
 * DECORATION.** `outreach_messages.number_id` is what makes *"which number sent
 * this"* answerable later, and re-deriving it from the e164 at write time would
 * be a second lookup that can disagree with the row this object came from — a
 * number released and reissued would resolve to the wrong one.
 */
final readonly class SendingNumber
{
    private function __construct(
        /** The `phone_numbers` row this send goes out on. */
        public int $id,
        /** The E.164 string the carrier is handed as the `from`. */
        public string $e164,
    ) {}

    /**
     * Mint one, or refuse because this number may not send.
     *
     * @internal Callers outside {@see NumberSelector} are a build failure — see
     *           the "nothing outside the selector mints a sending number" lint
     *           in tests/Feature/Architecture/MessagingTest.php.
     */
    public static function from(PhoneNumber $number): ?self
    {
        if (! $number->state->maySend()) {
            return null;
        }

        return new self($number->id, $number->e164);
    }
}
