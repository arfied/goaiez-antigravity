<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\TextNotDeliverable;
use App\Services\Sms\PlatformTexter;
use App\Services\Sms\SentText;
use App\Support\Identifier;

/**
 * Handing one text message to something that carries it.
 *
 * This is the transport, and it is deliberately the *only* thing at this level:
 * a number, a body, and whatever the carrier said back. **It knows nothing about
 * consent, suppression, opt-outs or the mini-TCPA windows, and it must never
 * learn.** Everything that decides *whether* to send lives one layer up in
 * {@see PlatformTexter}, which takes a `SendPermit` as a
 * required parameter (1566) — so an implementation of this interface cannot be
 * reached without going through that gate, because nothing outside `PlatformTexter`
 * is permitted to hold one. An `ArchitectureTest` lint holds that.
 *
 * WHY AN INTERFACE WHEN ONE IMPLEMENTATION IS THE REAL ONE. Not vendor
 * portability — `CLAUDE.md` §Vendors names Infobip as the single vendor for
 * numbers, SMS, WhatsApp and voice, and swapping it is not a planned event. The
 * reason is `BUILD-PLAN` §2.10.4's opening line: *"the log driver is what makes
 * this row testable at all, and it should be the first thing written: without it
 * every test below needs a carrier."* Slices 2 through 6 — STOP handling,
 * delivery receipts, the first invite, the register loaders, number health — all
 * need something that behaves like a send and reaches nobody. Row 4's 10DLC
 * campaign was filed and REJECTED (11617), and a *successful* filing still
 * takes ≈1–2 weeks (1562), so a seam
 * that only works once a carrier says yes would leave five buildable slices
 * waiting on a clock none of them depend on.
 *
 * ⚠️ **THERE IS NO SEGMENTATION, TRUNCATION OR TEMPLATING HERE, AND THE ABSENCE
 * IS DELIBERATE.** The 140-character composer is doc `43`, `43` extends `42`,
 * and `42` was never delivered (`BUILD-PLAN` §2.10.3 row 7+). A helpful
 * `substr($body, 0, 160)` added here would silently truncate somebody's message
 * and would be the composer, built by accident, in the layer least able to say
 * what a message is allowed to say. The body arrives finished.
 *
 * ✅ **`$reference` ARRIVED IN SLICE 3, WHICH IS WHEN 1570 SAID IT WOULD.** That
 * decision left it out because nothing would have passed one — *"a parameter
 * with no writer is decision 272's shape"* — and predicted that *"widening a
 * two-implementation interface with one caller is a small change when slice 3
 * needs it."* It was. The delivery-receipt webhook is the writer, and the
 * prediction is recorded here because this codebase more often finds the
 * opposite: a parameter added early for a caller that never came.
 *
 * ✅ **`$from` ARRIVED IN SLICE 6 ON THE SAME TERMS**, and the terms are what
 * make it justified rather than speculative: {@see PlatformTexter} passes one on
 * the day this ships, chosen by `NumberSelector` from a real row in a real
 * table. It is **not** the beginning of a routing layer here — this interface
 * still knows nothing about inventory, warmup, health or lanes, and it must not
 * learn. It is handed a string and told to use it.
 *
 * ✅ **`$mediaUrls` ARRIVED WITH THE L2 SENDER, ON THE SAME TERMS AGAIN**
 * (decision 2544): `PlatformMessageSender` passes one on the day it ships,
 * composed by L3's campaign runner, carried on `OutboundMessage::$mediaUrls`.
 *
 * ⚠️ **MMS IS THIS INTERFACE'S JOB AND NOT A SECOND INTERFACE'S, BECAUSE IT IS
 * THE SAME WIRE AND THE SAME NUMBER.** 2193: *"voice, SMS and MMS ride one
 * number with no capability flag"*, and an SMS, an MMS and the SMS+MMS pair are
 * each **one** send. ⚠️ **THIS SENTENCE READ "R9 PRICES … AS ONE SEND" AND THE
 * PRICING HALF IS GONE** (9182, and the replacement figure went stale again at
 * 12461). ⛔ **No price is stated here at all now**, because this argument never
 * needed one — it needs the *send* to be one, which no repricing has touched. A `MediaTexter` beside this one would
 * be a second transport contract for one message, and the layer above would
 * have to decide
 * which of the two to call — which is the routing decision this interface
 * exists not to contain. What changes below the interface is one endpoint;
 * {@see InfobipClient} chooses it from whether the list is empty.
 *
 * ⛔ **THE `@throws` BELOW IS TRUE OF ONE IMPLEMENTATION AND NOT OF THE OTHER,
 * AND THAT WAS INVISIBLE UNTIL 11300.** *"Never returns a failure as a value: a
 * send that did not happen must not be indistinguishable from one that did"* is
 * the rule, and `App\Services\Sms\LogTexter` breaks it by construction — deliberately, because a driver that reaches nobody is what
 * makes every slice from row 4 onwards buildable without a carrier. **So this
 * interface's guarantee is weaker than it reads**, and a caller that needs the
 * difference asks {@see ReachesRecipients} rather than believing the return
 * type. ⚠️ **The marker is NOT on this interface**, because widening a contract
 * with sixteen anonymous implementations in `tests/` fatals each of them at
 * runtime, one at a time, in a run that prints zero bytes — the same 1592 trap
 * the paragraph below records.
 *
 * ⚠️ **A NEW PARAMETER ON AN INTERFACE WITH ANONYMOUS IMPLEMENTATIONS IS
 * `CLAUDE.md`'s 1592 TRAP AND IT WAS MET AGAIN HERE.** An anonymous class left
 * on the old signature fatals at *runtime*, only when its own test executes,
 * killing the process before the reporter flushes — a run that prints zero
 * bytes and exits non-zero. Both test-side implementations
 * (`PlatformTexterTest`, `NumberInventoryTest`) were widened in the same commit.
 * Whoever adds the next parameter greps `implements Texter` first.
 */
interface Texter
{
    /**
     * Carry this message to this number, or fail loudly.
     *
     * ⚠️ **`$to` IS ALREADY NORMALISED AND IS NEVER RE-DERIVED HERE.** It arrives
     * as `SendPermit::$identifier` — the exact string suppression, the Do Not
     * Call registers and the reassigned-numbers check were all asked about. An
     * implementation that reformats it is asking the carrier to deliver to a
     * number no gate upstream ever saw.
     *
     * @param  string  $to  E.164, from {@see Identifier}.
     * @param  string  $body  Finished text. Not a template, not truncated here.
     * @param  string|null  $reference  An opaque string the carrier stores and
     *                                  hands back on the delivery receipt.
     *                                  ⚠️ **It travels to a third party and
     *                                  comes back unverified**, so it must
     *                                  never carry anything about a person and
     *                                  must never be trusted as authorisation
     *                                  on the way home — see
     *                                  {@see PlatformTexter} for what this one
     *                                  actually holds and why the receipt
     *                                  handler re-checks it rather than
     *                                  believing it.
     * @param  string|null  $from  One of **our** numbers, E.164, chosen by
     *                             `NumberSelector` from `phone_numbers` — never
     *                             a customer's and never derived here.
     *                             ⚠️ **Null means "this platform has no number
     *                             inventory yet"**, and an implementation that
     *                             reaches a carrier falls back to its configured
     *                             sender for that case only. It does **not** mean
     *                             "pick one" — the choice was already made, one
     *                             layer up, by the only thing that may make it.
     * @param  list<string>  $mediaUrls  URLs a carrier can fetch. Empty is a
     *                                   plain SMS; anything else makes this send
     *                                   an MMS. ⚠️ **URLs and never bytes** — a
     *                                   value that can hold a megabyte ends up
     *                                   in a queue payload, a log line and a
     *                                   failed-job row, and Infobip's own
     *                                   `MmsOutboundLinkSegment` takes a
     *                                   `contentUrl` for exactly that reason.
     *                                   ⚠️ **Not validated here.**
     *                                   `OutboundMessage::for()` already refuses
     *                                   anything `FILTER_VALIDATE_URL` rejects,
     *                                   and a second check that disagreed would
     *                                   be the worse of the two.
     *
     * @throws TextNotDeliverable when the message was not accepted. Never
     *                            returns a failure as a value: a send that did
     *                            not happen must not be indistinguishable from
     *                            one that did, which is the state the `log`
     *                            mail transport put this application in (700).
     */
    public function send(
        string $to,
        string $body,
        ?string $reference = null,
        ?string $from = null,
        array $mediaUrls = [],
    ): SentText;
}
