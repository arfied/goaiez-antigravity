<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Enums\MessageCostKind;
use App\Enums\OutreachStatus;
use App\Models\OutreachMessage;
use App\Services\Billing\MessageCostLedger;
use App\Services\Billing\MessageRates;
use App\Services\Config\DefaultsRegistry;
use App\Services\Messaging\SendingHealth;
use App\Services\Messaging\SendingRates;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What the carrier says happened to a message we sent — row 4 slice 3.
 *
 * ⚠️ **WITHOUT THIS, NO ROW EVER LEAVES `Sent`**, and `BUILD-PLAN` §2.10.3 names
 * the failure precisely: *"`MessageLog` reports a send that may never have
 * landed, which is decision 620's inversion — a reader showing a state nothing
 * updates."*
 *
 * ⛔ **THIS SENTENCE READ "EVERY ROW SITS `Queued` FOREVER" AND WENT ON TO SAY
 * THE LOG WOULD READ *"Waiting to send"* — CORRECTED 2026-08-22 (7481).** Both
 * halves were true when `BUILD-PLAN` was written and stopped being true at
 * 3030–3040, when `SendSettlement::settle()` began moving the row to `Sent` at
 * submission, before any receipt exists. `MessageLog` renders that as
 * **"Sent"**. ⛔ **The correction is not pedantry**: it is the symptom anybody
 * debugging this path would look for, it appears in this docblock, in
 * `InfobipClient`, in `InfobipDeliveryController` and in decision 7372, and
 * **the thing they would hunt for does not happen.** A total receipt blackout
 * looks like an ordinary message log.
 *
 * ## The tenant comes home in `callbackData`, and is re-checked rather than believed
 *
 * A receipt arrives with no tenant, like an inbound STOP — but unlike a STOP it
 * has to find **one specific row** in `outreach_messages`, which is tenant-owned
 * and RLS-`FORCE`d. With no tenant that query matches nothing, so slice 2's
 * answer (write something platform-scoped instead) is not available here: the
 * row already exists and belongs to somebody.
 *
 * So {@see PlatformTexter} sends the business id as the carrier's `callbackData`
 * and Infobip hands it back on the receipt. **It is used to establish a tenant
 * and never as proof of ownership.** The lookup that follows is by the carrier's
 * own message id, under RLS — so a forged or stale reference simply selects a
 * tenant whose rows do not contain that id, finds nothing, and is dropped. The
 * reference decides *which rows are visible*; it never asserts that a row is
 * anybody's.
 *
 * ⚠️ **A RECEIPT WE CANNOT PLACE IS DROPPED, NOT AN ERROR** — §2.10.4 names it:
 * *"a DLR for an unknown message id is dropped rather than throwing."* Four
 * ordinary states produce one, and none is a fault: a message sent before this
 * slice existed and so carries no reference; a receipt racing the transaction
 * that wrote the row; a test message sent from the Infobip console; and a
 * receipt for a message whose row was deleted with its tenant. Throwing would
 * make the carrier retry each of them for hours.
 *
 * ⛔ **DROPPED IS NOT THE SAME AS UNRECORDED, AND IT WAS UNRECORDED UNTIL 7480.**
 * Those four states are ordinary; a **fifth** produces the identical drop and is
 * the worst failure this application has — a carrier reporting under an id we
 * never stored, in which case *every* receipt lands here and the delivery
 * counters can never move again. {@see self::reportUnplaceable()} carries the
 * argument and the level.
 *
 * ⚠️ **AND IDEMPOTENCY IS THE ONE-WAY RULE RATHER THAN A DEDUPE TABLE.**
 * `OutreachStatus::canTransitionTo()` refuses a move out of a terminal state and
 * refuses a move to the state already held, so a redelivered receipt writes
 * nothing by construction. `inbound_messages` needed a unique index because a
 * replayed STOP would have written a second audit trail; a replayed receipt has
 * nothing to duplicate.
 *
 * ## This is `delivered`'s only writer, and `delivered` is the trip's denominator
 *
 * ⛔ **UNTIL 2026-08-12 NOTHING IN `app/` CALLED {@see SendingHealth} AT ALL**
 * (2496–2499). `SendingGuard::shouldTrip()` divides complaints by *delivered*,
 * `SendingRates::hasEnoughVolume()` reads the same figure — and both read a
 * permanently empty table, so the per-tenant pause and the platform halt of 2102
 * could never fire however the thresholds were set. 2499 names this path as one
 * of the two owed writers: *"until the DLR webhook calls
 * `recordDelivered()`/`recordFailed()` … no complaint-rate control in this
 * application can fire."*
 *
 * ⛔ **AND THE WRITER EXISTING IS NOT THE SAME AS THE COUNTER MOVING — 7480.**
 * Everything 2499 describes comes back, in full, if the receipts arriving here
 * cannot be placed: `delivered` stays at zero, `hasEnoughVolume()` answers false
 * for ever, the per-tenant trip and 2102's platform halt are both off, and
 * **every screen is green**, because a zero denominator renders as an honest
 * dash rather than as a fault. ⚠️ **`sent` is what tells the two apart** — it
 * keeps climbing — and until 7480 nothing in this application compared the two.
 * See {@see SendingRates::trafficWithoutOutcomes()}.
 *
 * ⚠️ **THE COUNTER MOVES ONLY WHERE THE ROW MOVED, WHICH IS WHY THE INCREMENT
 * SITS BELOW THE TRANSITION CHECK RATHER THAN BESIDE THE STATUS MAP.** A receipt
 * that named `DELIVERED` for a row already `Delivered` is refused above and must
 * not count — a carrier redelivering one receipt six times would otherwise
 * inflate the denominator sixfold and *suppress* the trip, which is the same
 * direction of failure as not counting at all and far harder to see. The
 * one-way rule is therefore the idempotency of the counter as well as of the row.
 */
final class DeliveryReceipts
{
    public function __construct(
        private readonly SendingHealth $health,
        private readonly MessageCostLedger $costs = new MessageCostLedger,
        private readonly MessageRates $rates = new MessageRates,
        private readonly DefaultsRegistry $defaults = new DefaultsRegistry,
    ) {}

    /**
     * Infobip's status groups, mapped onto ours.
     *
     * Read from the vendor's own `SmsMessageStatus` vocabulary on 2026-08-09.
     * ⚠️ **`PENDING` AND `ACCEPTED` ARE `Sent`, NOT `Delivered`** — they mean
     * the carrier has the message, which is the state the row is already in.
     * Mapping either to `Delivered` is how a message that never reached a
     * handset reports as delivered, and it is the single most tempting mistake
     * on this path because the receipt *arrived*, which feels like success.
     *
     * `EXPIRED` is a failure: the carrier gave up. It is listed rather than left
     * to the default so that a reader can see it was considered.
     *
     * @var array<string, OutreachStatus>
     */
    private const array GROUPS = [
        'DELIVERED' => OutreachStatus::Delivered,
        'UNDELIVERABLE' => OutreachStatus::Failed,
        'REJECTED' => OutreachStatus::Failed,
        'EXPIRED' => OutreachStatus::Failed,
        'PENDING' => OutreachStatus::Sent,
        'ACCEPTED' => OutreachStatus::Sent,
    ];

    /**
     * Apply one delivery receipt.
     *
     * Returns true when a row moved. False covers every ordinary reason it did
     * not — unknown message, unreadable status, a state already terminal — and
     * the caller answers 200 to all of them, because none is improved by the
     * carrier trying again.
     */
    public function apply(
        string $providerMessageId,
        ?string $groupName,
        ?string $reference,
        ?string $errorName = null,
    ): bool {
        $group = is_string($groupName) ? mb_strtoupper($groupName) : null;

        $status = $this->statusFor($groupName);

        if ($status === null) {
            // ⚠️ AN UNRECOGNISED GROUP IS DROPPED RATHER THAN DEFAULTED, and the
            // direction matters: defaulting to `Failed` would mark delivered
            // messages failed the day Infobip adds a status, and defaulting to
            // `Delivered` would claim a delivery nobody observed. Leaving the
            // row alone is the only answer that cannot be wrong.
            Log::info('A delivery receipt named a status this application does not map.', [
                'provider_message_id' => $providerMessageId,
                'group_name' => $groupName,
            ]);

            return false;
        }

        $businessId = $this->businessId($reference);

        if ($businessId === null) {
            return false;
        }

        return Tenancy::actingAs($businessId, function () use ($providerMessageId, $status, $errorName, $group): bool {
            $message = OutreachMessage::query()
                ->where('provider_msg_id', $providerMessageId)
                ->first();

            if (! $message instanceof OutreachMessage) {
                // The receipt named a message this tenant does not have. See the
                // class docblock — four ordinary states reach here, and a forged
                // reference is the fifth. All of them are a no-op.
                $this->reportUnplaceable($providerMessageId, $group);

                return false;
            }

            if (! $message->status->canTransitionTo($status)) {
                return false;
            }

            $message->status = $status;

            if ($errorName !== null && $status === OutreachStatus::Failed) {
                // ⚠️ THE VENDOR'S STATUS *NAME*, NEVER ITS DESCRIPTION. The name
                // is a fixed enumeration — `EC_ABSENT_SUBSCRIBER` and its
                // siblings; the description is prose that can quote the
                // destination number back at us. `MessageLog::explain()` never
                // shows this column to an owner either way, so what is stored
                // here is for whoever debugs it, and it must not become the
                // place a customer's number lands in a tenant-readable table.
                $message->error_message = $errorName;
            }

            $message->save();

            // ⚠️ **THE CHANNEL COMES OFF THE ROW, NEVER FROM A CONSTANT.** This
            // endpoint only ever receives SMS receipts today, and hardcoding
            // `OutreachChannel::Sms` here would file a future channel's outcomes
            // under SMS — where they would move the one denominator the
            // per-tenant trip divides by. The row already knows what it was.
            match ($status) {
                OutreachStatus::Delivered => $this->health->recordDelivered($message->channel),
                OutreachStatus::Failed => $this->health->recordFailed($message->channel),
                // `Sent` is the only other status this map produces, and it is
                // not an outcome — `PENDING`/`ACCEPTED` mean the carrier still
                // has the message. Counting it as delivered is the mistake the
                // GROUPS docblock above spends a paragraph on.
                default => null,
            };

            // ⛔ **THE INTERNAL COST BOOK, AND IT IS NOT THE COUNTER ABOVE**
            // (4805). `sending_health_windows` is a containment — it feeds the
            // per-tenant pause and 2102's platform halt. This is margin: what
            // the carrier charged us for a message that never arrived. They sit
            // one line apart and answer different questions, which is why both
            // are here and neither is derived from the other.
            //
            // ⚠️ **INSIDE THE `canTransitionTo()` GUARD FOR THE COUNTER'S OWN
            // REASON**, one step stronger. A carrier redelivering one receipt
            // six times must not book six fees; the one-way rule refuses the
            // repeat above, and `MessageCostLedger`'s idempotency key refuses it
            // again if it ever does not.
            if ($status === OutreachStatus::Failed) {
                $this->recordUndeliveredFee($message, $group);
            }

            return true;
        });
    }

    /**
     * Write down that a receipt arrived and had nowhere to land.
     *
     * ⛔ **THIS ARM RETURNED `false` WITH NO RECORD OF ANY KIND UNTIL 7480, AND
     * IT IS WHERE THE WORST FAILURE ON THIS PATH IS FIRST OBSERVABLE.** A
     * carrier that reports under an id this application never stored produces a
     * receipt that reaches exactly here, every time, for every message — and
     * before this line, the only trace anywhere on the platform was a counter
     * that **did not move**. ⚠️ **An absence is not a signal**: nothing
     * distinguishes *"the webhook is misconfigured and no receipt is arriving"*
     * from *"every receipt is arriving and none of them can be placed"*, and the
     * two need opposite fixes — check the notification profile, or stop sending
     * `destinations[].messageId`. **The presence of this line is the whole
     * difference**, and it is why the drop is recorded rather than counted.
     *
     * ⚠️ **`info` AND NOT `warning`, DELIBERATELY, AND THE SIBLING ARM SETTLES
     * IT.** The unrecognised-group drop twenty lines up is `info` for the same
     * reason: **one of these is not a fault**. The class docblock lists four
     * ordinary states that reach here — a message that predates this slice, a
     * receipt racing the transaction that wrote its row, a test message sent
     * from the Infobip console, and a receipt for a row deleted with its tenant
     * — and a `warning` on each would teach whoever reads the log to filter this
     * message out, which is 511's failure applied to the one line that would
     * have told them. ⛔ **THE SIGNAL IS THE RATIO AND NEVER THE LINE.** One is
     * ordinary; one per send is total.
     *
     * ⚠️ **AND THE COMPANION SIGNAL IS ON THE OTHER SIDE OF THE SAME FAILURE**
     * — {@see SendingGuard} logs a `warning` when the trip cannot fire on
     * traffic that has already gone out. That one is a fault and is levelled as
     * one; **the two lines together name the failure and its consequence**, and
     * neither on its own says a containment is off.
     *
     * ⚠️ **OUR OWN IDS AND THE VENDOR'S STATUS NAME, NEVER THE DESTINATION** —
     * the rule this class already applies to `error_message`. The recipient's
     * number is on the receipt payload and is deliberately not passed in here.
     */
    private function reportUnplaceable(string $providerMessageId, ?string $group): void
    {
        Log::info('A delivery receipt named a message this application cannot find.', [
            'provider_message_id' => $providerMessageId,
            'business_id' => Tenancy::id(),
            'group_name' => $group,
        ]);
    }

    /**
     * Book the carrier's fee for a message that never arrived — 2549's second
     * owed writer, closed at decision 4805.
     *
     * ⛔ **`MessageCostKind::UndeliveredFee` HAD NO WRITER FROM THE DAY THE ENUM
     * SHIPPED** (2555, 4689(a)) — 272's shape, and its tell exactly: the kind was
     * argued, seeded, priced and read by {@see MessageRates::pricedKinds()}, and
     * nothing in `app/` ever booked one.
     *
     * ## Two figures are withheld here, not one, and that is the whole design
     *
     * ⚠️ **THE RATE IS WITHHELD BY THE USUAL CONVENTION** — every key in this
     * schedule seeds to `0`, zero means unset rather than free, and
     * {@see MessageRates::costFor()} answers null so nothing is written (2547).
     *
     * ⛔ **AND THE BILLABLE STATUS SET IS WITHHELD SEPARATELY, BECAUSE A RATE
     * CANNOT EXPRESS IT.** {@see MessageCostKind::UndeliveredFee}'s own docblock
     * says the set is partial — *"a carrier still bills for **some** of these"* —
     * and this class maps three carrier groups onto `Failed`: `UNDELIVERABLE`,
     * `REJECTED` and `EXPIRED`. **Booking all three overstates and booking none
     * understates**, and the honest position is that which ones are billed is a
     * term of an Infobip contract this platform holds no copy of.
     * `REJECTED` is the case that decides it: a message the platform refused
     * before submission is *ordinarily* not billed — and "ordinarily" is exactly
     * the plausible-from-memory figure `MessageRates`' docblock exists to refuse
     * (255, 277, 684, 1349).
     *
     * ⛔ **SO THE SET IS AN OPS FIGURE SEEDED EMPTY, AND EMPTY BOOKS NOTHING.**
     * That is not a smaller version of guessing; it is the difference between a
     * book that is short and a book that is wrong. **A margin figure derived from
     * a guessed fee renders plausibly and coherently and is wrong**, on the one
     * ledger whose entire job is margin, and nothing downstream could tell.
     *
     * ⚠️ **THE CONSEQUENCE, SAID PLAINLY: UNTIL AN OPERATOR SETS BOTH, NO
     * UNDELIVERED FEE IS EVER BOOKED.** The mechanism is the deliverable; neither
     * number is ours. This is the same sentence `MessageRates` already carries,
     * and it is repeated here because this path needs *two* things set rather
     * than one and that is the part somebody would otherwise miss.
     *
     * ⚠️ **AN UNRECOGNISED GROUP NAME IS IGNORED RATHER THAN THROWN ON.** A
     * carrier adding a status, or an operator's typo, must never turn a webhook
     * into a 500 that Infobip answers by redelivering.
     *
     * ⚠️ **IT SWALLOWS ITS OWN FAILURE**, for 2547's rule and this webhook's:
     * bookkeeping must never cost us the status update beside it, which is the
     * thing that stops `MessageLog` telling an owner "Waiting to send" forever.
     *
     * @param  ?string  $group  The carrier's own status group name, upper-cased.
     */
    private function recordUndeliveredFee(OutreachMessage $message, ?string $group): void
    {
        if ($group === null || ! in_array($group, $this->billableGroups(), true)) {
            return;
        }

        try {
            // ⚠️ **NO SEGMENTS.** A fee is charged per undelivered message, not
            // per segment, and `message_cost_entries.segments` is nullable so
            // that "this is not billed by segment" is sayable. Zero would be a
            // claim the message had none, which is a different and false
            // statement.
            $costMillicents = $this->rates->costFor(MessageCostKind::UndeliveredFee);

            if ($costMillicents === null) {
                return;
            }

            $this->costs->record(
                kind: MessageCostKind::UndeliveredFee,
                costMillicents: $costMillicents,
                // ⚠️ **THE CARRIER'S OWN MESSAGE ID, WHICH IS THE SAME STRING ON
                // EVERY REDELIVERY** — the property a receipt-driven key must
                // have, since a receipt arriving twice is the ordinary case here
                // rather than the exceptional one. ⚠️ **The group is NOT in the
                // key**: a carrier that redelivered one receipt under two
                // different failure names would otherwise book the fee twice for
                // one undelivered message.
                idempotencyKey: 'dlr:'.$message->provider_msg_id.':'.MessageCostKind::UndeliveredFee->value,
                refType: OutreachMessage::CREDIT_REFERENCE_TYPE,
                refId: (int) $message->getKey(),
            );
        } catch (Throwable $e) {
            // ⚠️ **OUR OWN IDS, NEVER THE DESTINATION.** The same rule the
            // `error_message` column carries above: the vendor's status name is
            // a fixed enumeration and safe, and a recipient's number is not.
            Log::warning('A delivery receipt reported a failure and its carrier fee was not booked.', [
                'reason' => $e::class,
                'business_id' => Tenancy::id(),
                'outreach_message_id' => $message->getKey(),
                'group_name' => $group,
            ]);
        }
    }

    /**
     * Which carrier failure groups this operator has said they are billed for.
     *
     * ⚠️ **EMPTY IS THE SEEDED STATE AND MEANS "NOBODY HAS SAID"**, never "none
     * of them" — see {@see self::recordUndeliveredFee()} for why the difference
     * is the whole point. An empty list books nothing.
     *
     * ⚠️ **FILTERED AGAINST THIS CLASS'S OWN MAP RATHER THAN TAKEN ON TRUST**, so
     * a name that is not a failure group here — `DELIVERED`, a typo, a status
     * Infobip adds later — cannot start booking fees against successful sends.
     *
     * @return list<string>
     */
    private function billableGroups(): array
    {
        $configured = $this->defaults->stringOrNull('messaging.carrier_billable_undelivered_groups');

        if ($configured === null || trim($configured) === '') {
            return [];
        }

        $failureGroups = array_keys(array_filter(
            self::GROUPS,
            static fn (OutreachStatus $status): bool => $status === OutreachStatus::Failed,
        ));

        return array_values(array_intersect(
            array_map(
                static fn (string $name): string => mb_strtoupper(trim($name)),
                explode(',', $configured),
            ),
            $failureGroups,
        ));
    }

    /**
     * Which of our statuses a carrier status group means, or null.
     */
    private function statusFor(?string $groupName): ?OutreachStatus
    {
        if (! is_string($groupName) || $groupName === '') {
            return null;
        }

        return self::GROUPS[mb_strtoupper($groupName)] ?? null;
    }

    /**
     * The tenant this receipt claims to belong to, or null.
     *
     * ⚠️ **VALIDATED AS A POSITIVE INTEGER BEFORE IT REACHES `Tenancy`**, because
     * this string came back from a third party and is the one value on this path
     * that an attacker who defeated the signature would choose. `Tenancy::set()`
     * writes it into the Postgres session variable every RLS policy reads, so a
     * non-numeric value there is a malformed predicate rather than a narrower
     * one — and the failure mode of a malformed RLS predicate is not something
     * to discover from a webhook.
     */
    private function businessId(?string $reference): ?int
    {
        if ($reference === null || preg_match('/^[1-9][0-9]{0,17}$/', $reference) !== 1) {
            return null;
        }

        return (int) $reference;
    }
}
