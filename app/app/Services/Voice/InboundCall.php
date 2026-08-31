<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Enums\VoiceEventType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * One inbound call, as this application knows it.
 *
 * The payload half of T137 §2's day-0 **voice-event contract**. Every event in
 * `App\Events\Voice` carries one of these, so the conversation lane binds to
 * this shape once rather than to five slightly different constructor signatures.
 *
 * ## Inbound only, and the type system says so
 *
 * There is no direction field, because there is only one direction.
 * 4404 refuses the field here and `VoiceTest`'s project-wide call-direction
 * census fails the build on it — `29` §2.3 rule 13 itself was **overridden by
 * an owner ruling on 2026-08-25** (9363), so the refusal that holds is the one
 * with a mechanism under it. 2103 confirms the missed-call voice design does not touch it.
 * ⚠️ **A `direction` property would be the
 * first line of outbound calling**, written by somebody who thought they were
 * being general.
 *
 * ## What it carries about a person, and what it does not
 *
 * The caller's number is here because the SM-001 text-back goes to it and the
 * conversation lane threads on it. ⚠️ **Nothing else about the caller is**: no
 * carrier lookup, no location, no city inferred from the area code beyond the
 * timezone resolution the scheduler does separately, and — `29` §2 — **never a
 * raw IP and never anything assembled into a stable identifier**.
 *
 * ⛔ **THESE OBJECTS ARE NEVER BROADCAST.** They travel on Laravel's in-process
 * event bus and into queued jobs. A voice event on a websocket channel would put
 * a caller's mobile number on a client this application does not control, which
 * is the leak path the broadcasting rules exist for.
 *
 * ## The recording announcement is not a property here
 *
 * 2104: the announcement rides the pre-rendered greeting and is played on every
 * recorded call in every state, unconditionally. ⛔ **An `announcementPlayed`
 * flag would make it a thing that could be false** — and a nullable boolean
 * somebody has to set is exactly how "in every state" becomes "in most states".
 * The announcement is a property of the greeting audio, enforced where that
 * audio is chosen, not carried here to be checked.
 */
final readonly class InboundCall
{
    /**
     * @param  string  $providerCallId  The vendor's handle for this call. The
     *                                  join key for every later event about it,
     *                                  and the idempotency key for the webhook
     *                                  that delivers them — a voice webhook
     *                                  arrives twice as routinely as a delivery
     *                                  receipt does.
     * @param  int  $numberId  The `phone_numbers` row the call arrived on.
     *                         ⚠️ **This is what establishes the tenant** for a
     *                         webhook that arrives with none, the way the
     *                         delivery-receipt reference does — and like that
     *                         reference it is used to *establish* a tenant and
     *                         then everything is looked up under RLS, never
     *                         trusted as a claim that a row belongs to anybody.
     * @param  string  $from  The caller, E.164. ⚠️ **Normalised at the webhook
     *                        edge, once**, so that the number the text-back goes
     *                        to is the number consent and suppression are asked
     *                        about.
     * @param  string  $to  The tenant's own number, E.164. Dedicated number allocation: one
     *                      number per tenant, carrying voice, SMS and MMS —
     *                      which is why the text-back can come from the number
     *                      the caller just dialled.
     * @param  int|null  $customerId  The contact this caller resolved to, when
     *                                they resolved to one. **Null is the common
     *                                case and is not an error** — a first-time
     *                                caller has no row yet, and the lane that
     *                                handles the thread creates one.
     * @param  int|null  $durationSeconds  Null until the call has ended.
     */
    public function __construct(
        public VoiceEventType $type,
        public string $providerCallId,
        public int $numberId,
        public string $from,
        public string $to,
        public CarbonImmutable $occurredAt,
        public ?int $customerId = null,
        public ?int $durationSeconds = null,
    ) {
        if (trim($providerCallId) === '') {
            throw new InvalidArgumentException(
                'A call with no provider id can never have its later events matched to it, and the '
                .'webhook that delivers them cannot be made idempotent.'
            );
        }

        if (trim($from) === '' || trim($to) === '') {
            throw new InvalidArgumentException(
                'An inbound call needs both numbers. The text-back goes to one of them and leaves '
                .'from the other.'
            );
        }

        if ($durationSeconds !== null && $durationSeconds < 0) {
            throw new InvalidArgumentException('A call cannot have lasted a negative length of time.');
        }
    }
}
