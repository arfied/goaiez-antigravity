<?php

declare(strict_types=1);

namespace App\Services\Messaging\Outbound;

use App\Enums\OutreachStatus;
use App\Models\OutreachMessage;
use App\Services\Messaging\RateReading;
use App\Services\Messaging\SendingHealth;
use App\Services\Messaging\SendingRates;
use App\Services\Sms\DeliveryReceipts;

/**
 * The one place a message acquires a carrier handle — and therefore the one
 * place `sending_health_windows.sent` may move.
 *
 * ## Why this class exists at all, rather than two senders each doing it
 *
 * ⛔ **`SendingHealth::recordSent()` HAD NO WRITER ANYWHERE IN `app/` FOR THE
 * WHOLE OF THIS APPLICATION'S LIFE** (2496–2499, restated at 2535 and again at
 * 2977). {@see SendingRates::deliveryRateBp()} divides `delivered` by `sent`, so
 * with `sent` permanently zero the delivery rate was permanently zero too.
 *
 * ⚠️ **AND UNLIKE THE FOUR COUNTERS THE DLR AND STOP HANDLERS FIXED, THIS ONE
 * COULD NOT BE CLOSED BY PICKING A CALLER.** 2499 refused the campaign runner
 * and 2977 refused the invite path, both for the same reason, and it is the
 * reason this class is shaped the way it is: **a `sent` counter written by one
 * send path out of two is worse than one written by none.** Zero renders as an
 * em dash and an operator learns nothing from it; a partial denominator renders
 * as a confident percentage describing traffic that is not all the traffic.
 * 2637 built {@see RateReading} precisely so that "not
 * measured" could not masquerade as "measured at nought" — and a half-filled
 * denominator defeats that protection completely, because the denominator is no
 * longer zero and the em dash is gone.
 *
 * So the counter is not wired at a call site. It is wired to the *event*, and
 * this class is that event.
 *
 * ## The membership rule, which is the whole argument
 *
 * ⛔ **THE DENOMINATOR MUST HAVE THE SAME MEMBERSHIP AS THE NUMERATOR.**
 * `delivered` is only ever written for a row {@see DeliveryReceipts::apply()}
 * can find, and it finds rows by `provider_msg_id`. So the set of messages that
 * can ever produce a `delivered` is exactly the set of messages that were given
 * a carrier handle — no larger and no smaller. Counting `sent` anywhere else
 * would count a different population from the one the numerator is drawn from,
 * which is the specific way a delivery rate goes quietly wrong: count at
 * dispatch and every refused, rolled-back or credit-exhausted send drags the
 * rate down forever, with no receipt that could ever arrive to lift it again.
 *
 * ⚠️ **THIS IS ALSO THE ANSWER TO 2499 AND 2977, NOT AN EXCEPTION TO THEM.**
 * What both refused was a single writer that would make the rate read as though
 * one path were the platform's only traffic. The rule above is not a choice of
 * favourite caller — it is a definition of the measured population that the
 * numerator already obeys, and every send path inside that population
 * increments through here.
 *
 * ## What this rate therefore does not measure
 *
 * ⛔ **IT IS A CARRIER-SMS DELIVERY RATE AND MUST NEVER BE LABELLED OR READ AS
 * THE PLATFORM'S OVERALL ONE.** Two real send paths sit outside it by
 * construction, and both are outside the numerator too:
 *
 *   - **Compliance auto-replies** — the HELP, START and STOP acknowledgements
 *     sent by `PlatformTexter::replyToInbound()`. They write no
 *     `outreach_messages` row at all, so no delivery receipt can ever land on
 *     one. ⚠️ **And they frequently run with no tenant established**, because a
 *     STOP can arrive on a shared pool number that `TenantNumbers::tenantFor()`
 *     answers null for — `SendingHealth`'s increment calls `Tenancy::idOrFail()`,
 *     so counting there would throw inside a carrier webhook. **A throw inside a
 *     carrier webhook is a defect, not a guard.**
 * ⛔ **A SECOND PATH — "ALL PLATFORM EMAIL" — WAS LISTED HERE AND IS NO LONGER
 * OUTSIDE THE FIGURE, CORRECTED 2026-08-20 (6360).** It read: *"{@see
 * SendingHealth}'s own docblock records why the numerator is missing for mail:
 * nothing stores an SES message id against an `outreach_messages` row, so a
 * bounce cannot be routed home. Counting email `sent` while no email `delivered`
 * can ever be counted would show 0% delivery for a working mailer — far worse
 * than the em dash it shows today. **The two halves arrive together or not at
 * all.**"*
 *
 * ✅ **THE TWO HALVES ARRIVED TOGETHER, WHICH IS WHY THE CONDITION THAT
 * SENTENCE SET IS MET RATHER THAN WAIVED.** `MailSettlement` calls this class
 * from Laravel's `MessageSent` event with the id the transport named, and
 * `MailSendingHealth` writes `delivered` for a row found by that same id — so
 * the membership rule below holds on email exactly as it holds on SMS, and it
 * holds for the same reason rather than by analogy.
 *
 * ⚠️ **CUSTOMER EMAIL DOES NOT REACH THIS CLASS ON A TRANSPORT THAT CANNOT
 * REPORT BACK.** `PlatformMailer::sendToCustomer()` refuses outright unless
 * `MailDrivers::feedbackSignal()` answers `Typed` with an SNS topic behind it,
 * so the population of email rows that can be settled here is bounded upstream
 * by the same gate that makes the numerator possible. **No check of that lives
 * in this class**, deliberately: it would make the real gate unfalsifiable
 * (398) while proving nothing.
 *
 * ## Which is why the name is a settlement and not a "send"
 *
 * ⚠️ Nothing here decides whether to send, spends a credit, or talks to a
 * vendor. By the time it runs the carrier has already accepted the message and
 * named it. Its whole job is to write that one fact down in both of the places
 * it has to be written — the row and the counter — so that the two cannot
 * disagree with each other.
 *
 * ## The channel comes off the row, never from a constant
 *
 * ⚠️ **{@see DeliveryReceipts::apply()} FILES THE NUMERATOR UNDER
 * `$message->channel`, SO THE DENOMINATOR MUST READ THE SAME SOURCE.** A
 * hardcoded `OutreachChannel::Sms` here would work today, because both callers
 * are SMS-only, and would silently file a future channel's sends under SMS while
 * its deliveries went to their own window — two windows, each holding one half
 * of one fraction, both looking entirely plausible.
 *
 * ⚠️ **STATED PLAINLY BECAUSE IT CANNOT BE PROVEN HERE** (`CLAUDE.md`'s rule
 * about claims this harness cannot make): no second channel reaches this class
 * today, so no test drives that line red. It is reasoned from `DeliveryReceipts`'
 * matching behaviour, not demonstrated, and it is written down as reasoning
 * rather than dressed up as a covered case.
 *
 * ## Inside the caller's transaction, deliberately
 *
 * ⚠️ **BOTH CALLERS RUN THIS INSIDE AN OPEN TRANSACTION, AND THAT IS
 * LOAD-BEARING RATHER THAN INCIDENTAL.** `ReviewInviteSender` rolls back when
 * the texter refuses, and `SendingHealth`'s increment is an ordinary upsert on
 * the same connection — so a rolled-back send takes its own `sent` increment
 * with it. A counter incremented outside the transaction would survive the
 * rollback and count a message that does not exist, permanently, with nothing
 * anywhere that could ever correct it.
 */
final class SendSettlement
{
    public function __construct(private readonly SendingHealth $health) {}

    /**
     * Record that the carrier accepted this message and named it.
     *
     * @param  string  $providerMessageId  the carrier's handle — the join key
     *                                     every delivery receipt arrives with
     * @param  ?int  $numberId  which of our numbers it went out on; null on the
     *                          bootstrap path, where the driver used its
     *                          configured sender and no row names that number
     *                          — and null for **every** email, which goes out
     *                          from the platform's one sending domain and has
     *                          no `phone_numbers` row of any kind
     */
    public function settle(OutreachMessage $row, string $providerMessageId, ?int $numberId): void
    {
        // `Sent`, never `Delivered`: the carrier has the message, and whether a
        // handset ever saw it is the receipt webhook's separate fact. Reading an
        // acknowledgement as a delivery is decision 620's inversion, and it
        // would show every message as delivered the moment Infobip agreed to
        // think about it.
        $row->provider_msg_id = $providerMessageId;
        $row->number_id = $numberId;
        $row->status = OutreachStatus::Sent;
        $row->sent_at = now();
        $row->save();

        // ⚠️ **AFTER THE SAVE, SO THE COUNTER CANNOT OUTLIVE THE ROW.** If the
        // write above throws — a unique violation on the send key is the likely
        // one — nothing is counted, which is exactly right: `DeliveryReceipts`
        // can never find a row that was not written, so no numerator could ever
        // arrive to pair with that increment.
        $this->health->recordSent($row->channel);
    }
}
