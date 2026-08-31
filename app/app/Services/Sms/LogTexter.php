<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Contracts\SendLogReader;
use App\Contracts\Texter;
use App\Enums\OutreachChannel;
use App\Support\Identifier;
use Illuminate\Support\Facades\Log;

/**
 * The driver that reaches nobody, which is what makes the rest of row 4
 * buildable.
 *
 * `BUILD-PLAN` §2.10.4 opens with it: *"the log driver is what makes this row
 * testable at all, and it should be the first thing written: without it every
 * test below needs a carrier."* Slice 1 ships no send and that is its
 * deliverable (§2.10.3); this class is the proof the seam is real, and it is the
 * default driver everywhere until an operator names another one.
 *
 * ⚠️ **IT LOGS NEITHER THE NUMBER NOR THE BODY, AND THAT IS NOT AN OVERSIGHT TO
 * FIX WHILE DEBUGGING.** Laravel's `log` *mail* transport writes the whole
 * message — headers, addresses, body — into `storage/logs`, and copying that
 * behaviour here would put a stranger's mobile number and the text we sent them
 * into a file with no retention policy, no encryption and no audit, on every
 * developer machine and on the one server. `29` §2.4 permits the tenant id in
 * log context and nothing about the customer; `VendorLog`'s docblock builds its
 * context from an allowlist for the same reason and says why a denylist is the
 * wrong shape. So what is recorded is the *shape* of the send:
 *
 *   - the identifier **hash**, which is the same value `opt_outs` stores and is
 *     therefore already the safe form of this number in this system. It is
 *     enough to correlate two log lines about the same recipient and not enough
 *     to text them.
 *   - the body **length**, because "did the composer produce anything" is the
 *     question a developer actually has, and
 *   - the segment count, for the same reason — 160 GSM-7 characters is one
 *     segment and 161 is two, which is the number a rate governor will one day
 *     care about.
 *
 * ⚠️ **THE SEGMENT COUNT IS AN ESTIMATE AND IS LABELLED AS ONE.** Real GSM-03.38
 * segmentation depends on the alphabet, on seven characters that cost two units
 * each, and on a UCS-2 fallback the moment one character falls outside the
 * basic set. None of that is modelled here, because a number this class computes
 * for a log line must never become the number a billing or throttling decision
 * is made on — that belongs with the composer (`43`, blocked on `42`) and with
 * the rate governor, both of which are outside row 4 slice 1.
 *
 * ⚠️ **IT NEVER FAILS.** A driver that sends nothing has nothing to fail at, and
 * an artificial failure mode would make every later slice's tests depend on this
 * class's mood. The failure paths that matter are {@see InfobipClient}'s and are
 * driven there.
 *
 * ⛔ **AND THAT SENTENCE IS THE DEFECT ONE CHANNEL UP, WHICH IS WHY THIS CLASS
 * DOES NOT DECLARE `App\Contracts\ReachesRecipients`** (11300). {@see Texter}'s own
 * contract says a send that did not happen *"must not be indistinguishable from
 * one that did, which is the state the `log` mail transport put this
 * application in (700)"* — and the `SentText` returned below is exactly that
 * state, on purpose, because the alternative is the seam disappearing. **What
 * was wrong was not this class; it was a caller reading its answer as a fact.**
 * `PlatformTexter::sendToOwner()` decided whether an urgent page had reached an
 * account holder — and therefore whether a job retried, whether its claim was
 * spent for ever, and which of two sentences a member of the public who texted
 * *"there is a gas leak"* received — from a boolean this driver could only
 * answer one way.
 *
 * ⚠️ **NOTHING HERE CHANGED, AND THE ABSENCE OF A MARKER IS THE WHOLE OF THE
 * REPAIR.** `send()` still logs and still returns; the customer channel, the
 * compliance auto-replies and the operator bell all still run on it exactly as
 * they did, because that is what makes every slice from row 4 onwards testable
 * without a carrier. Only the owner channel now asks first.
 */
final class LogTexter implements SendLogReader, Texter
{
    /**
     * Characters per segment in a single-part GSM-7 message.
     *
     * A concatenated message spends 7 of its 160 units on the UDH that lets the
     * handset reassemble it, which is where 153 comes from. Both figures are
     * facts about GSM 03.38 rather than thresholds this project chose, so they
     * are constants and not registry keys — the same call `ZernioGbpClient`
     * makes about somebody else's documented `limit` ceiling.
     */
    private const int SINGLE_SEGMENT = 160;

    private const int CONCATENATED_SEGMENT = 153;

    /**
     * @param  list<string>  $mediaUrls
     */
    public function send(
        string $to,
        string $body,
        ?string $reference = null,
        ?string $from = null,
        array $mediaUrls = [],
    ): SentText {
        $length = mb_strlen($body);

        Log::info('sms log driver: message not sent', [
            // ⚠️ IN PLAIN TEXT, WHICH IS THE OPPOSITE RULE FROM `to_hash` ONE
            // LINE DOWN AND FOR THE REASON THE TWO LINES SIT TOGETHER: this is
            // one of OUR numbers, not a stranger's. `29` §2.4's restriction is
            // about the customer, and "which of our numbers did this go out on"
            // is the question slice 6 exists to make answerable. Null here is
            // the bootstrap case — no inventory — and is worth seeing.
            'from' => $from,
            // The hash, never the number. Identifier::hash() is the same
            // normalise-then-hash `opt_outs` keys on, so a null here means the
            // number could not be parsed — worth seeing, and the reason the
            // value is not asserted to be a string.
            'to_hash' => Identifier::hash($to, OutreachChannel::Sms),
            'body_length' => $length,
            'segments_estimated' => $this->segments($length),
            // ⚠️ WHETHER a reference was supplied, never the value. The value
            // is the business id, and a per-send log line naming the tenant of
            // every message is a busier claim than `29` §2.4's allowance is
            // worth spending here — "did the caller pass one" is the question a
            // developer wiring up delivery receipts actually has.
            'has_reference' => $reference !== null,
            // ⚠️ **THE COUNT, NEVER THE URLS, AND THE RULE IS THE SAME ONE THE
            // BODY OBEYS.** A media URL is one this application serves and is
            // therefore addressable — it names a tenant's asset and, on L3's
            // personalised-picture path, is minted per recipient, so a log line
            // full of them is a per-customer trail in a file with no retention
            // policy. "Was this an MMS, and how many parts" is the question a
            // developer actually has.
            'media_count' => count($mediaUrls),
        ]);

        return new SentText(
            // ⚠️ Prefixed, so a message id that reaches a delivery-receipt
            // handler or a support conversation announces which driver minted
            // it. A bare UUID here would be indistinguishable from a carrier's
            // handle, and slice 3 looking one up at Infobip would find nothing
            // and have no way to tell whether the receipt or the send was wrong.
            providerMessageId: 'log-'.hash('sha256', (string) hrtime(true)),
            vendorStatus: 'LOG_DRIVER_NOT_SENT',
        );
    }

    /**
     * The carrier has never heard of any of these, because there is no carrier.
     *
     * ⛔ **AN EMPTY ANSWER IS THE HONEST ONE AND IS NOT A STUB.** This driver
     * reaches nobody, so no message it "sent" exists in anybody's log, and
     * every handle asked about here is genuinely unaccounted for. The contract
     * is explicit that a missing key means *"this question has no answer"* and
     * never *"nothing was sent"*, so the caller draws exactly the right
     * conclusion: it leaves the row alone.
     *
     * ⚠️ **IT IS PAIRED WITH {@see self::send()} DELIBERATELY.** A driver that
     * carried nothing and could nonetheless *confirm* something would be the
     * `log` mail transport's silence with a second face — the failure
     * `TextNotDeliverable`'s docblock names as the reason this driver has to be
     * chosen by name rather than arrived at.
     *
     * @param  list<string>  $handles
     * @return array<string, SendLogEntry>
     */
    public function outcomesFor(array $handles): array
    {
        unset($handles);

        return [];
    }

    /**
     * Roughly how many parts this body would travel in.
     *
     * See the class docblock: an estimate, for a log line, and never an input to
     * a billing or throttling decision.
     */
    private function segments(int $length): int
    {
        if ($length <= self::SINGLE_SEGMENT) {
            return $length === 0 ? 0 : 1;
        }

        return (int) ceil($length / self::CONCATENATED_SEGMENT);
    }
}
