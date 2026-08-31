<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\CreditProduct;
use App\Enums\CreditPurchaseStatus;
use App\Enums\CreditTopUpTier;
use App\Exceptions\CreditChargeUnconfirmed;
use App\Exceptions\CreditPurchaseRefused;
use App\Models\AutoTopUpArrangement;
use App\Models\AutoTopUpCharge;
use App\Models\Business;
use App\Models\CreditPurchase;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Support\Billing\PurchaseConfirmation;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Automatic top-up: the standing arrangement, and the decision to act on it.
 *
 * The charge already existed — {@see CreditTopUps::chargeStoredCard()} bills the
 * card on file. What did not was the *agreement* that lets it fire with nobody
 * watching, which is the half that spends money on its own.
 *
 * ## Opt-in, and nothing here creates an arrangement
 *
 * ⛔ **`credits.auto_topup.enabled_by_default` SEEDS `false`, THE OWNER RESTATED
 * "DISABLED BY DEFAULT" ON 2026-08-14, AND {@see self::agree()} IS THE ONLY
 * WRITER.** It requires a {@see PurchaseConfirmation} it cannot fabricate. There
 * is no provisioning hook, no backfill and no default row — **an account that has
 * never chosen has no arrangement**, and the absence of a row is the only off
 * state that a bad default cannot flip.
 *
 * ⚠️ **WHICH MAKES THAT REGISTRY KEY INERT, AND NOTHING IN `app/` READS IT.**
 * Nothing should: an operator turning it on cannot conjure the confirmation
 * `agree()` requires, so the only thing it could ever do is arrange charges
 * nobody agreed to. It is described as inert in the manifest rather than deleted
 * so that the argument survives where the seed is, and this line is the second
 * half of that — a key with no reader that reads as a switch is 272's shape with
 * a card charge attached.
 *
 * ## CONFIRM is on the arrangement, not on each charge
 *
 * 2064 is explicit, and it is what an arrangement *is*: agreement once to a
 * standing instruction. So the confirmation is recorded here rather than at each
 * charge — and {@see self::confirmationFor()} derives each charge's confirmation
 * from it, carrying the wording the tenant actually agreed to.
 *
 * ⚠️ **A CHARGE LARGER THAN THE AGREED FIGURE IS REFUSED RATHER THAN MADE.**
 * Prices are admin-editable (3415), so the SKU really can move after the
 * agreement, and a bigger charge is one nobody agreed to. It suspends the
 * arrangement and asks the tenant again, which is the only honest answer.
 *
 * ## An unanswered charge stops it on the first occurrence
 *
 * ⛔ **A GATEWAY TIMEOUT USED TO BYPASS BOTH THE CEILING AND THE IN-FLIGHT
 * GUARD, AND THAT WAS THIS FILE'S DEFECT RATHER THAN THE FUNDER'S** (3861–3866).
 * `CreditTopUps` recorded a lost response as `failed`; no charge row was written,
 * so the month's spending did not count it; the purchase was terminal, so the
 * reconciliation sweep never revisited it; and this service counted it as one
 * decline of three. **Three nights of lost responses was $150 charged under an
 * agreement whose stored wording says $50.**
 *
 * ⚠️ **THE ANSWER IS BOTH HALVES.** The charge is recorded even though nobody
 * knows whether it worked, so the ceiling counts money that may have moved; and
 * the arrangement suspends itself immediately, because *"we do not know whether
 * we charged you"* is not a thing to discover a second time and because counting
 * alone does not bound a large ceiling.
 *
 * ## What it refuses, in order
 *
 * Cheapest and most durable first, which is `SendingGuard`'s ordering rule: the
 * agreement standing, then the plan, then the ceiling, then an open charge, then
 * the balance. **Each is driven independently by test** — 398's shape is an outer
 * guard refusing first and leaving the rest unfalsifiable, and five conditions in
 * a row is exactly where that hides.
 */
final class AutoTopUps
{
    /**
     * ⛔ **A PURCHASE IN THESE STATES MEANS A CHARGE IS ALREADY IN FLIGHT.**
     *
     * `CreditPurchases` refuses a duplicate *settlement*; nothing there stops a
     * second scheduler run opening a second purchase for the same low balance.
     * The window is real — the balance does not move until the webhook settles,
     * so every run between the charge and the webhook sees the same low figure
     * and would charge again.
     */
    private const array IN_FLIGHT = [
        CreditPurchaseStatus::Pending,
        CreditPurchaseStatus::Authorized,
    ];

    /**
     * ⛔ **THE TWO REASONS THIS SERVICE WRITES, AS THE THING A SCREEN MATCHES ON.**
     * {@see self::stoppedCause()} is how a customer-facing surface gets from a
     * suspension to a sentence, and it has to be *this* file that says which is
     * which — a screen inferring the cause from the failure count told every
     * tenant "the price changed" about their own money the moment a third cause
     * existed. Prefixes, because the card reason names how many times.
     */
    private const string STOPPED_BY_CARD = 'the card on file was declined';

    private const string STOPPED_BY_PRICE = 'the price of this top-up rose above the amount that was agreed';

    /**
     * ⛔ **THE THIRD REASON, AND IT IS THE ONE THAT STOPS ON THE FIRST OCCURRENCE.**
     * A charge the gateway never answered may have taken the money. Counting it as
     * one of three declines would let the next two nights charge the same card for
     * the same unknown reason, and the account's own agreement would be
     * arithmetically impossible to keep. See {@see self::chargeIfNeeded()}.
     */
    private const string STOPPED_BY_UNCONFIRMED = 'a charge was sent to the gateway and never answered';

    public function __construct(
        private readonly CreditLedger $credits = new CreditLedger,
        private readonly CreditTopUps $topUps = new CreditTopUps,
        private readonly TopUpCatalog $catalog = new TopUpCatalog,
        private readonly Subscriptions $subscriptions = new Subscriptions,
        private readonly DefaultsRegistry $defaults = new DefaultsRegistry,
        private readonly AuditService $audit = new AuditService,
    ) {}

    /**
     * Record a tenant's agreement to be charged automatically.
     *
     * ⛔ **THE ONLY WRITER, AND IT TAKES A CONFIRMATION IT CANNOT INVENT.**
     * `PurchaseConfirmation::given()` refuses an empty actor, a wording under ten
     * characters and a non-positive amount, so an arrangement cannot be created
     * by a caller that has not actually shown somebody something.
     *
     * @throws CreditPurchaseRefused
     */
    public function agree(
        Business $business,
        CreditProduct $product,
        CreditTopUpTier $tier,
        Money $ceiling,
        PurchaseConfirmation $confirmation,
    ): AutoTopUpArrangement {
        $sku = $this->catalog->sku($product, $tier);

        if (! $confirmation->amountShown->equals($sku->price)) {
            throw CreditPurchaseRefused::because(
                'The amount shown when this arrangement was agreed does not match what the '
                .'top-up now costs. An arrangement is agreement to a repeating charge, so '
                .'the figure has to be the one they saw.'
            );
        }

        if ($ceiling->minorUnits < $sku->price->minorUnits) {
            throw CreditPurchaseRefused::because(
                'A ceiling below the price of one top-up is an arrangement that can never '
                .'fire. Cancelling is how automatic top-up is turned off; a ceiling that '
                .'silently does nothing would read as working on every screen.'
            );
        }

        // ⚠️ The partial unique index refuses a second live arrangement per
        // product; this is the readable refusal in front of it.
        $existing = $this->liveArrangement($business, $product);

        if ($existing instanceof AutoTopUpArrangement) {
            throw CreditPurchaseRefused::because(
                'This account already has automatic top-up arranged for that product. '
                .'Cancel the existing arrangement before agreeing a new one, so there is '
                .'one record of what was agreed rather than two.'
            );
        }

        $arrangement = new AutoTopUpArrangement;
        $arrangement->business_id = $business->id;
        $arrangement->product = $product;
        $arrangement->tier = $tier;
        $arrangement->ceiling_cents = $ceiling->minorUnits;
        $arrangement->currency = $ceiling->currency;
        $arrangement->agreed_actor = $confirmation->actor;
        $arrangement->agreed_amount_cents = $confirmation->amountShown->minorUnits;
        $arrangement->agreement_wording = $confirmation->wording;
        $arrangement->agreed_at = $confirmation->confirmedAt;
        $arrangement->save();

        return $arrangement;
    }

    /**
     * Withdraw an arrangement. The row is kept — "did they ever agree to this" is
     * asked about periods that have already passed.
     *
     * ⛔ **THE ACTOR IS REQUIRED, BECAUSE THE ROW DOES NOT CARRY ONE.**
     * {@see self::agree()} writes four columns saying who agreed and to what; this
     * wrote one timestamp, so *"who turned off automatic top-up"* had no answer
     * anywhere — and it is the question asked by a tenant whose credit ran out and
     * who did not switch it off themselves. `CLAUDE.md`'s *"every sensitive action
     * → append-only audit log"* is the rule; `audit_log` is where it goes rather
     * than a fifth column, because the model refuses updates and deletes and
     * outlives the row it describes.
     *
     * @param  string  $actor  `user:14`, or a fixed label for automation —
     *                         `AuditService`'s vocabulary, never a nullable id.
     */
    public function cancel(AutoTopUpArrangement $arrangement, string $actor): AutoTopUpArrangement
    {
        $arrangement->cancelled_at = Carbon::now();
        $arrangement->save();

        $this->audit->record('billing.auto_topup_cancelled', $actor, $arrangement, [
            'product' => $arrangement->product->value,
            'ceiling_cents' => $arrangement->ceiling_cents,
            'currency' => $arrangement->currency,
        ]);

        return $arrangement;
    }

    /**
     * Why this arrangement stopped itself, as one of a fixed set — or null if it
     * has not stopped.
     *
     * ⛔ **DERIVED FROM THE REASON THAT WAS STORED, NEVER FROM THE FAILURE
     * COUNT.** The count answers a different question ("is this card failing
     * now"), and it happens to be at its limit for exactly one of the causes —
     * so reading it as the cause makes every *other* cause, including every one
     * added later, indistinguishable from the price having moved. A tenant told
     * "the price changed" about a suspension that was nothing of the kind is
     * being told something untrue about their own money.
     *
     * ⚠️ **`other` IS A REAL ANSWER AND NOT A FALLBACK.** A third reason written
     * here must reach a caller as "we stopped, ask us" rather than borrowing one
     * of the two sentences that already exist.
     *
     * @return 'card'|'price'|'unconfirmed'|'other'|null
     */
    public function stoppedCause(AutoTopUpArrangement $arrangement): ?string
    {
        if (! $arrangement->stopped_at instanceof Carbon) {
            return null;
        }

        $reason = (string) $arrangement->stopped_reason;

        if (str_starts_with($reason, self::STOPPED_BY_CARD)) {
            return 'card';
        }

        if (str_starts_with($reason, self::STOPPED_BY_PRICE)) {
            return 'price';
        }

        // ⛔ ITS OWN CAUSE RATHER THAN `other`, BECAUSE THE ADVICE IS DIFFERENT AND
        // BECAUSE IT IS THE ONE WHERE MONEY MAY ALREADY HAVE GONE. "Nothing has
        // been charged" is the sentence the other two carry and is the one thing
        // that cannot be said here.
        if (str_starts_with($reason, self::STOPPED_BY_UNCONFIRMED)) {
            return 'unconfirmed';
        }

        return 'other';
    }

    /**
     * Why this arrangement may not charge right now, or null if it may.
     *
     * ⚠️ **A SENTENCE RATHER THAN A BOOLEAN**, on 2454's rule: these are states of
     * the account, and the operator reading the log is the person who has to
     * explain to a tenant why their balance did not top up.
     */
    public function refusalFor(AutoTopUpArrangement $arrangement): ?string
    {
        if (! $arrangement->isLive()) {
            return $arrangement->cancelled_at instanceof Carbon
                ? 'automatic top-up was cancelled for this account'
                : 'automatic top-up is suspended: '.(string) $arrangement->stopped_reason;
        }

        $business = Business::query()->whereKey($arrangement->business_id)->firstOrFail();

        // ⛔ CHARGING A CANCELLED ACCOUNT FOR CREDIT IT CANNOT SPEND IS A REFUND
        // REQUEST. 3441 makes purchased credit unspendable while the plan is
        // inactive, so an automatic charge into that state takes money for
        // something the tenant provably cannot use. The gate belongs on the
        // purchase as well as on the spend.
        if (! $this->subscriptions->isEntitled($business)) {
            return 'the plan is not running, and credit cannot be spent without one';
        }

        // ⛔ THE CEILING BOUNDS THE CHARGE ABOUT TO BE MADE, NOT ONLY THE ONES
        // ALREADY MADE. 3306 is "the most an account will automatically be
        // charged", which is a limit on the total — so the question is whether
        // this top-up would carry the month past it. Asking only whether the
        // ceiling had *already* been reached would let the last charge of the
        // month cross it by the entire price of a top-up: a $120 ceiling charging
        // $50 three times spends $150, and the tenant set $120 precisely so that
        // could not happen. {@see self::agree()} refuses a ceiling below one
        // top-up, so this can never refuse the first charge of a window.
        $sku = $this->catalog->sku($arrangement->product, $arrangement->tier);

        if ($this->spentThisWindow($arrangement)->minorUnits + $sku->price->minorUnits > $arrangement->ceiling_cents) {
            return 'another top-up this month would pass the limit set for this account';
        }

        if ($this->hasChargeInFlight($arrangement)) {
            return 'an automatic top-up is already in progress for this account';
        }

        if (! $this->balanceIsLow($arrangement)) {
            return 'the balance has not fallen far enough to need topping up';
        }

        return null;
    }

    /**
     * Charge one arrangement, if it should be charged.
     *
     * Returns the purchase when one was opened, null when the arrangement was
     * refused for any of the reasons above.
     *
     * ⚠️ **A FAILED CHARGE NEVER PROPAGATES.** This runs on a schedule behind
     * every low balance in the system, and 2904's rule is that an exhausted or
     * unfundable balance degrades rather than throwing — a card decline must not
     * break the send that noticed the balance was low.
     */
    public function chargeIfNeeded(AutoTopUpArrangement $arrangement): ?CreditPurchase
    {
        if ($this->refusalFor($arrangement) !== null) {
            return null;
        }

        $business = Business::query()->whereKey($arrangement->business_id)->firstOrFail();
        $sku = $this->catalog->sku($arrangement->product, $arrangement->tier);

        // ⛔ THE PRICE MOVED AFTER THEY AGREED. Admin-editable prices (3415) mean
        // this is reachable, and a charge larger than the figure on the screen is
        // one nobody agreed to. Suspending asks them again rather than guessing
        // that they would not mind.
        if ($sku->price->minorUnits > $arrangement->agreed_amount_cents) {
            $this->suspend(
                $arrangement,
                self::STOPPED_BY_PRICE.', so it needs agreeing again'
            );

            return null;
        }

        try {
            $purchase = $this->topUps->chargeStoredCard(
                $business,
                $arrangement->product,
                $arrangement->tier,
                $this->confirmationFor($arrangement),
            );
        } catch (CreditChargeUnconfirmed $e) {
            // ⛔ THE MONEY MAY HAVE MOVED, SO IT IS COUNTED AND THE ARRANGEMENT
            // STOPS. Both halves matter and neither is enough alone.
            //
            // Counted: the charge row is what {@see self::spentThisWindow()} adds
            // up and what {@see self::hasChargeInFlight()} joins through, so
            // without it a lost response was money the ceiling could not see —
            // three of them was $150 against an agreement that says $50, and the
            // page's flat "never more than $X a month" was a promise this service
            // could break in the dark.
            //
            // Stopped on the first one, not the third: a decline is evidence the
            // card is bad and three of them is a pattern, but *"we do not know
            // whether we charged you"* is not something to find out twice. It also
            // bounds a large ceiling, which counting alone does not — a $500 limit
            // would otherwise absorb ten unanswered charges before it bound
            // anything.
            $this->recordCharge($arrangement, $e->purchase, $sku->price->minorUnits, $sku->price->currency);

            $this->suspend($arrangement, self::STOPPED_BY_UNCONFIRMED.', so it needs agreeing again');

            return null;
        } catch (Throwable $e) {
            $this->recordFailure($arrangement, $e);

            return null;
        }

        $this->recordCharge($arrangement, $purchase, $sku->price->minorUnits, $sku->price->currency);

        if ($arrangement->consecutive_failures > 0) {
            $arrangement->consecutive_failures = 0;
            $arrangement->save();
        }

        return $purchase;
    }

    /**
     * ⛔ **THE CLAIM: THIS ACCOUNT SPENT THIS MONEY AUTOMATICALLY THIS MONTH.** It
     * is what makes a second run a no-op and what the monthly limit is counted
     * from, and it is written for a charge whose *outcome* is unknown as readily
     * as for one that succeeded — because the ceiling is a promise about money
     * that may have left the card, not about answers we received.
     *
     * ⚠️ **WRITTEN ON ITS OWN, IN NO TRANSACTION WITH ANYTHING.** The purchase
     * already exists and the money has already moved or may have, so this row has
     * to survive everything after it failing.
     */
    private function recordCharge(
        AutoTopUpArrangement $arrangement,
        CreditPurchase $purchase,
        int $priceCents,
        string $currency,
    ): void {
        $charge = new AutoTopUpCharge;
        $charge->business_id = $arrangement->business_id;
        $charge->auto_topup_arrangement_id = $arrangement->id;
        $charge->credit_purchase_id = $purchase->id;
        $charge->price_cents = $priceCents;
        $charge->currency = $currency;
        $charge->save();
    }

    /**
     * Every live arrangement in this tenant, for the scheduled sweep.
     *
     * @return list<AutoTopUpArrangement>
     */
    public function liveArrangements(): array
    {
        return array_values(
            AutoTopUpArrangement::query()
                ->whereNull('cancelled_at')
                ->whereNull('stopped_at')
                ->orderBy('id')
                ->get()
                ->all()
        );
    }

    private function liveArrangement(Business $business, CreditProduct $product): ?AutoTopUpArrangement
    {
        return AutoTopUpArrangement::query()
            ->where('business_id', $business->id)
            ->where('product', $product->value)
            ->whereNull('cancelled_at')
            ->first();
    }

    /**
     * The confirmation each automatic charge carries, derived from the
     * arrangement rather than invented.
     *
     * ⛔ **THIS IS WHY CONFIRM ON THE ARRANGEMENT IS ENOUGH** (2064). Every charge
     * reaches `CreditPurchases::open()` with the wording the tenant actually read
     * and the amount they actually saw, so the purchase record answers a
     * chargeback the same way a manual one does — it simply points at an
     * agreement made earlier rather than a click made now.
     */
    private function confirmationFor(AutoTopUpArrangement $arrangement): PurchaseConfirmation
    {
        return PurchaseConfirmation::given(
            $arrangement->agreed_actor,
            $arrangement->agreedAmount(),
            $arrangement->agreement_wording,
        );
    }

    /**
     * What this account has already spent automatically on this product inside
     * the current window.
     *
     * ⚠️ **THE WINDOW IS THE CALENDAR MONTH** — see the migration's docblock and
     * 3440. The ruling says "the most before it stops" and does not say over
     * what; the calendar month is taken so the ceiling and the monthly grant
     * share one clock.
     *
     * ⚠️ **IT COUNTS CHARGES ATTEMPTED, NOT MONEY PROVEN TO HAVE LEFT THE CARD,
     * AND THIS DOCBLOCK SAID THE OPPOSITE** (3869). A row here can belong to a
     * purchase later declined at the notification, voided or refunded, and — since
     * the fix wave — to one whose outcome nobody knows at all. The ceiling counts
     * every one of them, so the error is always in the direction of charging
     * *less* than the limit allows. **That is the safe side and it is the reason
     * the sentence is corrected rather than the query**: waiting for proof before
     * counting is exactly what let three unanswered charges spend $150 under a $50
     * agreement.
     *
     * ⚠️ **IT SUMS `price_cents` ACROSS ROWS WITHOUT COMPARING THEIR `currency`**
     * (3870), which is inert today because `billing.currency` is one platform-wide
     * key, and is one of 2058's breaks when it is not. Adding `Money` rather than
     * integers is the fix, and it belongs with whoever makes a currency a tenant's
     * own.
     */
    private function spentThisWindow(AutoTopUpArrangement $arrangement): Money
    {
        $cents = (int) $this->chargesForProduct($arrangement)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('price_cents');

        return Money::of($cents, $arrangement->currency);
    }

    /**
     * ⛔ A CHARGE ALREADY IN FLIGHT. The balance does not move until the webhook
     * settles, so without this every run between the charge and the webhook sees
     * the same low figure and charges again.
     *
     * ⚠️ **THERE IS NO TIME BOUND ON IT, AND SINCE THE FIX WAVE IT SPANS CANCELLED
     * ARRANGEMENTS TOO** (3871). A purchase stranded at `pending` or `authorized`
     * therefore blocks this account's automatic buying for that product **for
     * ever**, and re-agreeing no longer escapes it — 3727's stranded-purchase case
     * with its last exit closed. **That is the intended direction**: the exit was
     * "charge the card again and hope", and the containment is what stops a lost
     * notification becoming a second charge. ⛔ **What clears it is
     * {@see PurchaseReconciliation}**, which settles or abandons the row; the two
     * remain the pair 3727 named. **What is still owed is a sentence on the
     * screen**, which shows this account as "On" with a balance that does not
     * move and no words for why.
     */
    private function hasChargeInFlight(AutoTopUpArrangement $arrangement): bool
    {
        return $this->chargesForProduct($arrangement)
            ->whereHas('purchase', function (Builder $query): void {
                $query->whereIn('status', array_map(
                    static fn (CreditPurchaseStatus $status): string => $status->value,
                    self::IN_FLIGHT,
                ));
            })
            ->exists();
    }

    /**
     * Every automatic charge this account has ever made for one product, under
     * whichever arrangement made it.
     *
     * ⛔ **KEYED ON THE ACCOUNT AND THE PRODUCT, NEVER ON THE ARRANGEMENT'S ID,
     * AND THAT IS THE WHOLE OF BOTH DEFECTS ABOVE.** Changing the monthly limit
     * cancels the old arrangement and agrees a new one (3507 keeps the old row),
     * so an id-keyed count starts again at zero on every change: a tenant who had
     * spent $500 of a $500 limit and then *lowered* it to $50 would be charged
     * another $50 the same month, and could repeat it without bound — $550 spent
     * in a month whose stated limit is $50, caused by trying to spend less. The
     * same reset cleared the in-flight guard, so a re-ceiling between the charge
     * and the webhook charged the same low balance twice, and it wiped 3727's
     * containment of a purchase stranded at `Authorized`.
     *
     * ⚠️ **A CANCELLED ARRANGEMENT'S CHARGES STILL COUNT, DELIBERATELY.** The
     * money left the tenant's card this month whoever agreed to it; the ceiling
     * is a promise about the month, not about a row.
     *
     * @return Builder<AutoTopUpCharge>
     */
    private function chargesForProduct(AutoTopUpArrangement $arrangement): Builder
    {
        // ⚠️ A SUBQUERY RATHER THAN `whereHas()`, FOR ONE PLAIN REASON: the
        // relation's builder is generic, so a `where('product', …)` inside its
        // closure is unverifiable at level 8, while `AutoTopUpArrangement::query()`
        // knows what its own columns are. Both compile to the same `IN (…)`.
        return AutoTopUpCharge::query()
            ->where('business_id', $arrangement->business_id)
            ->whereIn(
                'auto_topup_arrangement_id',
                AutoTopUpArrangement::query()
                    ->where('product', $arrangement->product->value)
                    ->select('id'),
            );
    }

    /**
     * ⛔ AGAINST THE TOTAL SPENDABLE BALANCE, NOT THE TOP-UP POOL ALONE. A spend
     * draws the monthly grant first and spills into top-up (3307), so an empty
     * top-up pool is the ordinary state of an account inside its allowance —
     * triggering on it would charge every tenant on the first of the month.
     */
    private function balanceIsLow(AutoTopUpArrangement $arrangement): bool
    {
        $threshold = $this->defaults->int($arrangement->product->autoTopUpThresholdKey());

        return $this->credits->balance($arrangement->product) <= $threshold;
    }

    private function recordFailure(AutoTopUpArrangement $arrangement, Throwable $e): void
    {
        $arrangement->consecutive_failures++;

        if ($arrangement->consecutive_failures >= AutoTopUpArrangement::FAILURES_BEFORE_SUSPENSION) {
            // ⚠️ THE REASON, NEVER THE VENDOR'S MESSAGE TEXT. A gateway's `text`
            // quotes the value it rejected — a card's last four, an email
            // address — and this string lands on a row an operator reads.
            $this->suspend(
                $arrangement,
                self::STOPPED_BY_CARD.' '.$arrangement->consecutive_failures.' times running'
            );

            return;
        }

        $arrangement->save();
    }

    /**
     * ⛔ **AUDITED, BECAUSE THE ROW RECORDS *WHAT* AND NOT *WHO*.** `system` is the
     * honest actor — nobody pressed anything — and `CLAUDE.md`'s *"every sensitive
     * action → append-only audit log"* covers stopping a standing charge as much
     * as starting one. The reason string is the operator's; it is safe here and
     * would not be on a customer's screen ({@see self::stoppedCause()}).
     */
    private function suspend(AutoTopUpArrangement $arrangement, string $reason): void
    {
        $arrangement->stopped_at = Carbon::now();
        $arrangement->stopped_reason = $reason;
        $arrangement->save();

        $this->audit->record('billing.auto_topup_stopped', 'system', $arrangement, [
            'product' => $arrangement->product->value,
            'reason' => $reason,
            'consecutive_failures' => $arrangement->consecutive_failures,
        ]);
    }
}
