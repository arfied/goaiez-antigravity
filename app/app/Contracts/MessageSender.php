<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\OutreachPurpose;
use App\Exceptions\TextNotDeliverable;
use App\Services\Messaging\Outbound\OutboundMessage;
use App\Services\Messaging\Outbound\SendOutcome;

/**
 * The one thing a feature calls to send a message on any channel.
 *
 * **This is the interface L3 (campaigns) and L5 (conversation) build against.**
 * T137 §2's day-0 send-driver contract: SMS and email share the permit and the
 * suppression path, so they share this.
 *
 * ## What it guarantees, so that no caller has to
 *
 *   1. **A permit, or the call cannot be expressed.** The parameter is an
 *      {@see OutboundMessage}, which cannot be constructed without a
 *      `SendPermit`, which only `ConsentService` mints. 285's mechanism,
 *      widened to every channel.
 *   2. **Idempotency.** One `SendKey` per send. A retry, a double-click, or a
 *      job replayed after a lost acknowledgement returns
 *      `SendOutcomeStatus::Duplicate` and sends nothing. T137 §3.1.
 *   3. **The credit debit is transactional with the send.** ⛔ **One credit for
 *      the text and one for the media — 9182, confirmed 9193, reversing R9's
 *      *"an SMS, an MMS, or the SMS+MMS pair each count once"*.** A plain text
 *      is one; a text carrying a picture is two. ⚠️ **What is still one is the
 *      *send*** — one `SendKey`, one row, one `deliver()` — so an implementation
 *      that debited per transmission would still be wrong. ⚠️ **The
 *      ledger is L1's `CreditLedger` and there is exactly one** — an
 *      implementation of this interface calls it, and never keeps its own count,
 *      and never its own arithmetic either: the quantity is
 *      `App\Services\Billing\SendCredits`' to decide from the send's own row.
 *   4. **The kill switches.** `sms.enabled` / the mail equivalent, the
 *      **per-tenant sending pause** and the **global halt** — which 2102
 *      requires trip automatically on a complaint-rate threshold, because a kill
 *      switch that needs somebody awake is not the mitigation 2098 can rely on.
 *   5. **Recipient-local quiet hours, for marketing only.** ⛔ **`SL-2`'s rule
 *      and the T69 law: missed-call text-backs and service replies are NEVER
 *      quiet-gated.** The axis is {@see OutreachPurpose} on the
 *      message, and `Transactional` is never held.
 *   6. **The `outreach_messages` row, written before the wire call, inside the
 *      transaction.** `BUILD-PLAN` §2.10.1's third property: a record filed
 *      after the fact loses every send the process dies in the middle of.
 *
 * ## What it deliberately does not do
 *
 * **It does not retry.** Backoff with jitter belongs to the queued job that
 * called it — synchronous retry inside a send is how one message becomes three,
 * because a vendor timeout is not a vendor failure. **It does not compose.**
 * The ≤159 composer, the name normaliser and the short-link minting all run
 * before this is called. **It does not schedule.** A caller wanting a send at a
 * future time enqueues a job; a sender that could hold a message would be a
 * second scheduler beside the one that already exists.
 *
 * ⚠️ **THE 10DLC BRAND AND CAMPAIGN ARE REGISTERED, AND THAT CHANGES NOTHING ON
 * THIS INTERFACE.** 2110 is resolved in T137 R1's favour — the registration is
 * in hand and 2087's "not started" was the stale record. So a real carrier is
 * reachable, and the only things standing between this method and a handset are
 * a driver binding and a kill switch. **What registration does not do is make a
 * send consented** (2100, R6's own clarification): 10DLC is carrier route
 * approval, complaint-rate physics apply to a registered campaign exactly as
 * they do to an unregistered one, and every guarantee numbered above stands
 * unaltered. ⛔ **It makes guarantee 4 more load-bearing rather than less**:
 * these sends now really do leave over the GOAIEZ brand from our own pool, so
 * the complaint rate really does accrue across every tenant at once (2101).
 */
interface MessageSender
{
    /**
     * Send one message, having decided everything above.
     *
     * @return SendOutcome ⚠️ **Refusal is an answer, not an error** —
     *                     `ReviewInviteSender`'s fourth load-bearing property.
     *                     Quiet hours, a suppression that landed between the
     *                     permit and the send, an exhausted credit balance, a
     *                     paused tenant: all of these are frequent, expected,
     *                     and carry their reason. Callers **must** check
     *                     `wasSent()` rather than assuming, because
     *                     `Duplicate` is not a send either.
     *
     * @throws TextNotDeliverable when the vendor could not be reached. The
     *                            calling job retries with backoff; this method
     *                            never does.
     */
    public function send(OutboundMessage $message): SendOutcome;
}
