<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\BillingTerm;
use App\Enums\Plan;
use App\Exceptions\AmbiguousPlanOffer;
use App\Models\PlanOffer;
use App\Models\Subscription;
use App\Services\Config\DefaultsRegistry;
use App\Support\Instalments;
use App\Support\Money;
use App\Support\PlanQuote;
use App\Support\PlanSelection;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * What a selection costs, and the payments it is collected in (decision 2680).
 *
 * ⚠️ **`App\Support\Instalments` HAD NO CALLER IN `app/` AND THIS IS IT.** The
 * split has been derived correctly and tested since row 22 slice D, and decision
 * 2149 said so out loud — *"`App\Support\Instalments` derives the payments and
 * nothing charges them"* — because the annual offer had no screen. That is
 * `CLAUDE.md`'s writerless-control shape with the arrow reversed, and the owner's
 * answer of 2026-08-12 was to build the missing machinery before the missing
 * price.
 *
 * ## Every figure is read, none is written
 *
 * The four numbers this class needs — the two prices, the add-on prices, the
 * cycle length — all come from the registry, and `RegistryTest` fails the build
 * on any of them typed as a literal anywhere else (512, and 2055 for the
 * per-payment cents). Nothing here seeds, caches or rounds one.
 *
 * ## The remainder rides the final payment, and that is not this class's call
 *
 * {@see Instalments} implements `CLAUDE.md`'s ordering — the smaller payments
 * first, the remainder last — and decision 2135 records that the rest of the
 * record disagrees with itself about it. **Nothing here re-opens that**: the
 * ordering is the split's, this class only decides how many payments there are
 * and when they fall.
 *
 * ⚠️ **THE SPLIT IS PER SKU AND THEN SUMMED, WHICH IS NOT THE SAME AS SPLITTING
 * THE TOTAL.** 2055 states the instalments as "$332.33 primary, $166.33 per
 * additional location" — two derivations, each with its own remainder — and for
 * one location the two methods happen to agree. They do not agree in general: a
 * total split three ways carries at most two cents of remainder however many
 * locations are on it, while three locations each carrying their own cent is
 * three. Splitting the total would quietly re-price the add-on the day somebody
 * bought four.
 *
 * ## ⛔ TWO KINDS OF PRICE LIVE HERE AND CONFUSING THEM IS THE DEFECT 3444 NAMES
 *
 * They are told apart by what you hand in, and there is no way to ask the wrong
 * question by accident:
 *
 *   - **You hand in a {@see PlanSelection}** — something a person is *about to
 *     buy* — and you get **today's registry price**. `priceFor()`,
 *     `paymentsFor()`, `scheduleFor()`. Right for checkout, the marketing home
 *     and an Ops price screen; **wrong for anyone who has already bought**.
 *   - **You hand in a {@see Subscription}** — something a person *already has* —
 *     and you get **the price they agreed to**, from the row. `agreedPriceFor()`,
 *     `agreedAdditionalLocationPriceFor()`, `agreedPaymentsFor()`. Right for a
 *     renewal notice, a billing page, an invoice line.
 *
 * ⚠️ **THE PAIRING IS `paymentsFor()` / `agreedPaymentsFor()` AND THE FIRST OF
 * THOSE IS NOT ON THE LINT'S FORBIDDEN LIST** (4840). It calls `unitPriceFor()`
 * and `additionalLocationPriceFor()` *in here*, where the enumerated-file lint
 * cannot see them, so a billing page asking a `PlanSelection` for its instalments
 * quotes today's offer with the build green. `agreedPaymentsFor()` is the
 * row-reading twin.
 *
 * ⚠️ **THE TWO ANSWERS ARE THE SAME NUMBER TODAY AND THAT IS WHY THIS IS WRITTEN
 * DOWN RATHER THAN LEFT TO READ FROM THE CODE.** No plan price has ever moved, so
 * a call to the wrong one is invisible in every test and on every screen — until
 * the first price change, when the owner's ruling (3443) makes them diverge for
 * every existing customer at once.
 *
 * ## ⛔ AND SINCE T176 P1 THERE IS A THIRD THING: AN OFFER
 *
 * `plan_offers` holds the founder rates (2090). Every "today's price" method
 * below asks {@see PlanOffers} for the offer live on this term **first** and
 * falls back to the registry when there is none — so the price a page quotes, the
 * price a card is charged and the price stored on the row are one number by
 * construction rather than by three call sites agreeing.
 *
 * ⚠️ **`agreedPriceFor()` DOES NOT CONSULT AN OFFER AND MUST NOT.** An offer is a
 * fact about what is being sold *now*; a subscription's price is a fact about what
 * was agreed *then*, and it is on the row. Reading the live offer for an existing
 * customer would make the founder rate expire out from under them the day the
 * window closed, which is 3443 broken in the one method written to honour it.
 *
 * ⛔ **THAT SENTENCE WAS FALSE FOR THE WHOLE OF P1 AND IS TRUE SINCE 4341.** The
 * method's own *fallback* — the arm a row with four null columns takes — called
 * `priceFor()`, which consults the offer. So the one claim this docblock made
 * about offers was contradicted by the one branch nothing in the suite drove with
 * an offer live. It is a protection layer asserted before it was true (314–316),
 * inside the class that quotes 3444 twice.
 *
 * ⚠️ **THE OFFER IS RESOLVED ONCE PER INSTANCE.** A checkout renders the price,
 * the per-payment schedule and the disclosure from three separate calls; a window
 * closing between two of them would split one purchase across two prices, and the
 * page would be internally inconsistent with nothing to blame. See
 * {@see self::offerFor()}.
 *
 * ⛔ **AND "PER INSTANCE" IS EXACTLY AS FAR AS THAT GOES, WHICH IS LESS FAR THAN
 * THE PARAGRAPH ABOVE READS (4346).** Nothing binds this class in the container,
 * so a checkout that constructs one and a writer that constructs another are two
 * memos — and a purchase priced by one, sent to a vendor, and then *recorded* by
 * the other is one purchase at two prices with a network round trip in between.
 * The memo prevented the split inside one object and read as though it prevented
 * the split altogether, which is 314–316 in the sentence that argues for it.
 * {@see self::quote()} is the answer: the numbers are carried to the writer on a
 * {@see PlanQuote} rather than looked up a second time.
 */
final class PlanCharges
{
    /**
     * How many payments the **retail** annual price is collected in.
     *
     * ⚠️ **THREE, FROM `CLAUDE.md`'s COMMERCIAL MODEL AND DECISION 2055** —
     * *"The annual plan may be paid in three instalments"*. It is the owner's
     * figure and it is written here rather than in `DefaultsRegistry` for
     * decision 2142's reason inverted: the registry holds figures an operator may
     * move, and moving the count of payments on a **live** subscription is not
     * something either vendor can express (Authorize.Net will not update an
     * interval after creation at all). A subscription is created with the count
     * it was sold with, for its whole life.
     *
     * ⛔ **THE COUNT IS PER OFFER AND THIS IS NOW ONLY THE RETAIL ONE** (2092,
     * 2754). T176 R10's founder annual is **two** payments, and since P1 that
     * figure lives on the offer row — `plan_offers.instalment_payments` — exactly
     * as 2754 instructed. This constant is what applies when no offer is live.
     * ⚠️ **Three and two are not a contradiction to be tidied**: they price
     * different products, and `RegistryTest` fails the build if somebody
     * harmonises them.
     */
    public const int PAYMENTS = 3;

    /**
     * The live offers, resolved once for the life of this instance.
     *
     * ⚠️ **`null` MEANS "NOT LOOKED UP YET" AND `[]` MEANS "NO OFFER IS LIVE",
     * AND THE TWO ARE NOT THE SAME.** An empty array is the ordinary answer — it
     * is what every request sees after R18's flip — so a memo that treated it as
     * "unresolved" would re-query on every call and lose the whole point of
     * {@see self::offerFor()}. `??=` assigns on null only, which is what keeps
     * them apart.
     *
     * @var array<string, PlanOffer>|null
     */
    private ?array $liveOffers = null;

    public function __construct(
        private readonly DefaultsRegistry $registry = new DefaultsRegistry,
        private readonly PlanOffers $offers = new PlanOffers,
    ) {}

    /**
     * The whole price of a selection: the base plan plus each extra location.
     */
    public function priceFor(PlanSelection $selection): Money
    {
        return $this->quote($selection)->total();
    }

    /**
     * Both of a selection's rates, resolved together and carryable (4346).
     *
     * ⛔ **THIS IS WHAT A CHECKOUT HANDS ITS WRITER**, rather than handing over
     * the selection and letting a second `PlanCharges` price it again. Two
     * instances with two memos priced one purchase either side of a network round
     * trip — see {@see PlanQuote} for the subscription that came out of it — and
     * a value that carries the numbers makes the second lookup impossible instead
     * of unlikely.
     */
    public function quote(PlanSelection $selection): PlanQuote
    {
        return new PlanQuote(
            $selection,
            $this->unitPriceFor($selection),
            $this->additionalLocationPriceFor($selection),
        );
    }

    /**
     * What one location on this term costs **today** — the registry figure.
     *
     * ⚠️ **THIS IS THE NUMBER THAT GETS STORED ON A SUBSCRIPTION AT SIGNUP** —
     * {@see Subscriptions::recordQuotedSelection()} writes it, out of the
     * {@see PlanQuote} {@see self::quote()} builds. Every other reader wants
     * `priceFor()`, which adds the locations on.
     *
     * ⛔ **AND IT IS NOT PUBLIC FOR THAT ONE CALLER, WHICH THIS PARAGRAPH SAID
     * UNTIL 2026-08-29** (12330). The citation named `Subscriptions::recordAgreedPrice()`,
     * **a method that has never existed on that class**, and the *"one caller"*
     * beside it was false as well: `AuthorizeNetCheckoutController` reads this
     * method directly to print the base price on the checkout page, which is a
     * live quote and is correct. **A reader checking the grandfathering claim
     * would have gone looking for a writer under the wrong name and found
     * nothing** — which is the shape, not the typo.
     *
     * ⛔ **AND THAT IS WHY THE OFFER HAS TO BE READ HERE RATHER THAN AT CHECKOUT.**
     * If the founder rate were applied by the page and not by this method, the
     * page would quote $99.99, the gateway would be handed $99.99 by a separate
     * calculation, and the row would store the registry's $179.99 — which
     * `RenewalReminders` would then quote back to a founder tenant for ever
     * (3444's defect, with the offer as its new cause).
     */
    public function unitPriceFor(PlanSelection $selection): Money
    {
        $offer = $this->offerFor($selection->term);

        return $offer instanceof PlanOffer
            ? $offer->price()
            : Money::of($this->cents($selection->priceKey()), $this->currency());
    }

    /**
     * What each location beyond the first costs **today**, on this term.
     *
     * ⚠️ **QUOTED EVEN WHEN NONE ARE BEING BOUGHT**, because 3443 grandfathers the
     * add-on rate as well as the plan price: a tenant who signs up with one
     * location and adds a second next year is owed the rate they agreed to, and a
     * row storing only the plan price could not answer that. The column is
     * therefore written on every subscription, not only on the ones with locations
     * on them.
     */
    public function additionalLocationPriceFor(PlanSelection $selection): Money
    {
        $offer = $this->offerFor($selection->term);

        return $offer instanceof PlanOffer
            ? $offer->additionalLocationPrice()
            : Money::of($this->cents($selection->term->additionalLocationKey()), $this->currency());
    }

    /**
     * What a subscription that **already exists** costs its owner (3443, 3444).
     *
     * ⛔ **THE WHOLE OF THE GRANDFATHERING RULING IS THIS METHOD AND ITS WRITER.**
     * The ruling is that an existing customer keeps the price they signed up at
     * for ever, whatever the registry later says. The gateway already honours that
     * without being asked — it holds the subscription and its webhooks are the
     * source of truth (2056), so a registry edit cannot reprice anybody. What a
     * registry edit *can* do is change what we **quote** them, and
     * `RenewalReminders` quotes an amount in a statutory notice. This is where
     * that number comes from.
     *
     * ⚠️ **IT NEVER TOUCHES THE REGISTRY FOR A ROW THAT HAS A STORED PRICE**, and
     * that is the design rather than an optimisation. A "read the row, fall back
     * per column" version would be the same defect wearing a fallback: the first
     * price change would leave a grandfathered row quoting its own plan price
     * beside today's add-on rate, and the total would be a number nobody ever
     * agreed to.
     *
     * ## ⚠️ A row that predates the columns has no stored price, and falls back
     *
     * The columns are nullable with no backfill and the migration argues why: a
     * `pending_checkout` row is on no price at all, and a backfill `UPDATE` in a
     * migration would match zero rows under `FORCE ROW LEVEL SECURITY` and report
     * success. So a row written before 2026-08-14 falls back to the registry —
     * which is **exactly what every surface did before this slice**, so the
     * fallback is the present behaviour rather than a new risk.
     *
     * ⛔ **THE FALLBACK READS THE REGISTRY AND NEVER AN OFFER (4341), AND IT WENT
     * IN THROUGH `priceFor()` FIRST — WHICH IS 3444 REBUILT INSIDE THE METHOD
     * WRITTEN TO PREVENT IT.** `priceFor()` consults the live offer, so from the
     * moment `plan_offers` held a live row this fallback stopped quoting the
     * retail schedule and started quoting the founder rate: a tenant who bought
     * the annual plan on 2026-08-13 — after the term landed (2680), before the
     * agreed-price columns did (3443) — has four null columns and would have been
     * sent a California ARL renewal notice quoting the **founder** annual while
     * the gateway charged the **retail** one. **Both halves right on their own
     * screen**, which is 3444 word for word with the offer as its new cause. The figures are therefore composed
     * here from `cents()` and `currency()` directly, and the docblock line that
     * used to sit below — *"no subscription that exists today was sold at a price
     * other than the current one"* — was true while the registry was the only
     * source and is exactly the sentence that stopped the next reader looking.
     *
     * ⚠️ **AND IT STOPS BEING FREE ON THE DAY A PRICE MOVES.** The population it
     * can affect is bounded and small — the annual term landed 2026-08-12 (2680)
     * and the figures were set 2026-08-11 (2053, 2054) — but it is **not empty**,
     * and an offer opening is a price moving for everybody who buys next. Whoever
     * makes the first price change should look at how many rows still carry a null
     * `price_cents` and decide, with the real figures in hand, whether those
     * tenants are quoted the old price from a backfill or the new one from this
     * fallback. **That is a question about people rather than about code**, and it
     * is recorded here rather than answered.
     *
     * ⚠️ **THE FALLBACK ASSUMES ZERO ADDITIONAL LOCATIONS**, which is not a guess:
     * every subscription this application can sell today carries zero — both
     * checkout paths build a `PlanSelection` from a term and an instalment flag
     * and nothing else — and `RenewalReminders` already reasoned exactly that way
     * before this slice. Counting a tenant's `locations` rows *now* would invent a
     * figure nobody was charged.
     */
    public function agreedPriceFor(Subscription $subscription): Money
    {
        $cents = $subscription->price_cents;
        $perLocation = $subscription->additional_location_cents;
        $locations = $subscription->additional_locations;
        $currency = $subscription->price_currency;

        if ($cents === null || $perLocation === null || $locations === null || $currency === null) {
            // ⚠️ All four are checked even though the database CHECK
            // (`subscriptions_agreed_price_is_whole`) makes a partial group
            // unrepresentable. The CHECK is what guarantees it; this is what makes
            // the guarantee legible here, and it is the difference between a null
            // reaching `Money::of()` and a fallback happening on purpose.
            //
            // ⛔ COMPOSED HERE RATHER THAN THROUGH `priceFor()`, WHICH CONSULTS
            // THE OFFER. See the docblock: routing this through the "today's
            // price" side is 3444 rebuilt with an offer as its cause. The
            // selection carries zero additional locations by construction, so
            // this is the base price and nothing else.
            $selection = $this->selectionBehind($subscription);

            return Money::of($this->cents($selection->priceKey()), $this->currency());
        }

        $base = Money::of($cents, $currency);

        if ($locations === 0) {
            return $base;
        }

        return $base->plus(Money::of($perLocation, $currency)->times($locations));
    }

    /**
     * What an extra location costs **this** tenant — the rate they agreed to (4347).
     *
     * ⛔ **THE TWO LOCATION SCREENS QUOTED TODAY'S RATE AND THE WRITER RECORDS THE
     * STORED ONE.** `Subscriptions::recordAdditionalLocations()` deliberately
     * never re-reads a price — its own docblock argues that re-reading it "would
     * silently reprice them at the moment somebody was doing them a favour" — so
     * an operator screen quoting `additionalLocationPriceFor()` was showing a
     * figure the very next line of code refuses to use. It was invisible while the
     * registry was the only source **and while R10 prices the founder add-on
     * identically to retail**; the first offer whose add-on genuinely differs
     * makes the screen and the row disagree by that difference.
     *
     * ⚠️ **A ROW WITH NOTHING STORED FALLS BACK TO TODAY'S RATE, OFFER INCLUDED**,
     * and that is not the same choice as {@see self::agreedPriceFor()}'s. There is
     * no agreement to honour on such a row — `recordAdditionalLocations()` refuses
     * it outright — so the only honest figure is what a location would be sold at
     * now, which is what a checkout would charge.
     */
    public function agreedAdditionalLocationPriceFor(
        ?Subscription $subscription,
        PlanSelection $selection,
    ): Money {
        $cents = $subscription?->additional_location_cents;
        $currency = $subscription?->price_currency;

        if ($cents === null || $currency === null) {
            return $this->additionalLocationPriceFor($selection);
        }

        return Money::of($cents, $currency);
    }

    /**
     * The payments an **existing** subscription is collected in, in order (3443, 4840).
     *
     * ⛔ **{@see self::paymentsFor()} WITH A `PlanSelection` IS THE WRONG METHOD FOR
     * SOMEBODY WHO HAS ALREADY BOUGHT, AND IT IS NOT ON THE LINT'S FORBIDDEN LIST.**
     * `BillingTest`'s "a surface quoting an existing subscription reads the agreed
     * price" bans `->priceFor(`, `->unitPriceFor(` and `->additionalLocationPriceFor(`
     * in the enumerated files — `paymentsFor()` calls two of those *inside this
     * class*, so an account screen that asked for a payment breakdown would have
     * quoted today's registry (and today's offer) with the lint green. This is the
     * row-reading twin, and it exists so that the honest call is available at the
     * moment somebody needs the breakdown rather than a diff later.
     *
     * ⚠️ **THE COUNT IS `subscriptions.instalment_payments` AND NEVER
     * {@see self::paymentCountFor()}.** That method asks the live offer, so a
     * founder tenant on two payments would be shown three the day the window
     * closed, and a retail tenant three payments would be shown two the day one
     * opened — decision 4643's defect, which shipped once already on the card page
     * as an unconditional "three payments". `null` means the plan was not sold in
     * instalments at all and the answer is one payment, which is also the right
     * answer for every monthly subscription.
     *
     * ⚠️ **SPLIT PER SKU AND SUMMED POSITIONALLY, EXACTLY AS `paymentsFor()` DOES**,
     * because splitting the *total* is a different schedule. Each SKU's remainder
     * rides its own final payment, so two remainders can add up past a whole
     * payment and land differently from one remainder taken on the sum — the two
     * schedules agree at one extra location on today's figures and disagree at
     * two. They total the same year either way, and the gateway was handed the
     * per-SKU one, so quoting the other would show a customer payments their card
     * will not see. `AccountBillingSurfaceTest` pins the disagreement with the
     * figures read from the registry rather than typed.
     *
     * ⚠️ **THE FALLBACK IS {@see self::agreedPriceFor()}'s AND NOT `paymentsFor()`'s**
     * — composed from `cents()` and `currency()` directly, never through the
     * offer-consulting side (4341). A row with four null columns is a row sold
     * before the columns existed; quoting it a founder instalment it never agreed
     * to is 3444 with an offer as its cause.
     *
     * @return list<Money> summing exactly to {@see self::agreedPriceFor()}
     */
    public function agreedPaymentsFor(Subscription $subscription): array
    {
        $count = $subscription->instalment_payments ?? 1;

        $stored = $subscription->price_cents;
        $storedPerLocation = $subscription->additional_location_cents;
        $storedLocations = $subscription->additional_locations;
        $storedCurrency = $subscription->price_currency;

        if (
            $stored === null
            || $storedPerLocation === null
            || $storedLocations === null
            || $storedCurrency === null
        ) {
            // All four, for `agreedPriceFor()`'s reason: the database CHECK makes a
            // partial group unrepresentable and this is what makes that legible.
            $selection = $this->selectionBehind($subscription);

            $unit = $this->cents($selection->priceKey());
            $perLocation = 0;
            $locations = 0;
            $currency = $this->currency();
        } else {
            $unit = $stored;
            $perLocation = $storedPerLocation;
            $locations = $storedLocations;
            $currency = $storedCurrency;
        }

        $payments = Instalments::split($unit, $count);

        if ($locations > 0) {
            $perLocationPayments = Instalments::split($perLocation, $count);

            foreach ($payments as $index => $amount) {
                $payments[$index] = $amount + ($perLocationPayments[$index] * $locations);
            }
        }

        return array_map(
            static fn (int $minorUnits): Money => Money::of($minorUnits, $currency),
            $payments,
        );
    }

    /**
     * The selection a row with no stored price was sold on, as far as it can say.
     *
     * ⚠️ **ONLY EVER REACHED FROM THE FALLBACK.** `term` has been written on every
     * subscription since 2680 and `instalment_payments` with it, so this
     * reconstructs the two facts such a row does carry and nothing else. A row
     * with no term at all — a `pending_checkout` that never reached a gateway — is
     * read as monthly, which is the same default both checkout paths apply to a
     * caller that made no choice.
     */
    private function selectionBehind(Subscription $subscription): PlanSelection
    {
        if ($subscription->term !== BillingTerm::Annual) {
            return PlanSelection::monthly();
        }

        return $subscription->instalment_payments === null
            ? PlanSelection::annual()
            : PlanSelection::annualInInstalments();
    }

    /**
     * The payments this selection is collected in, in the order they are taken.
     *
     * One payment for anything not bought in instalments — so a caller never has
     * to branch on `inInstalments` to find out what the first charge is.
     *
     * @return list<Money>
     */
    public function paymentsFor(PlanSelection $selection): array
    {
        if (! $selection->inInstalments) {
            return [$this->priceFor($selection)];
        }

        $unit = $this->unitPriceFor($selection);
        $perLocationPrice = $this->additionalLocationPriceFor($selection);
        $count = $this->paymentCountFor($selection);

        // Per SKU, then summed positionally. See the class docblock for why this
        // is not `Instalments::split($total, $count)`.
        $payments = Instalments::split($unit->minorUnits, $count);

        if ($selection->additionalLocations > 0) {
            $perLocation = Instalments::split($perLocationPrice->minorUnits, $count);

            foreach ($payments as $index => $amount) {
                $payments[$index] = $amount + ($perLocation[$index] * $selection->additionalLocations);
            }
        }

        return array_map(
            static fn (int $minorUnits): Money => Money::of($minorUnits, $unit->currency),
            $payments,
        );
    }

    /**
     * How many payments this selection is collected in.
     *
     * ⛔ **THE OFFER's COUNT, OR {@see self::PAYMENTS} WHEN THERE IS NO OFFER**
     * (2092, 2754). Three for retail, two for the founder annual, and the two
     * figures are not a discrepancy — see the constant's docblock.
     *
     * ⚠️ **AN OFFER THAT DOES NOT SELL INSTALMENTS FALLS BACK TO THE RETAIL COUNT
     * RATHER THAN REFUSING**, and that is reachable only through a caller that
     * built an instalment selection on a term the offer prices in one payment. The
     * alternative — throwing — would turn a catalogue that says nothing about
     * instalments into a checkout that 500s, where this quotes the schedule the
     * plan has always had.
     */
    public function paymentCountFor(PlanSelection $selection): int
    {
        $offer = $this->offerFor($selection->term);

        if (! $offer instanceof PlanOffer) {
            return self::PAYMENTS;
        }

        return $offer->instalment_payments ?? self::PAYMENTS;
    }

    /**
     * When each payment falls, given the day the first one is taken.
     *
     * ⚠️ **THE SPACING IS `billing.cycle_days` AND IT WAS FOUND RATHER THAN
     * CHOSEN** — decision 2599's move, on the slice that had to pick a tick
     * cadence. Nobody has ruled on how far apart three instalments sit, and
     * `29`'s "when ambiguous, choose the smaller support surface" points at the
     * interval this application already bills on: one billing cycle, which is
     * decision 147's 30 days and is the only spacing either vendor can express
     * without a second registry key. ⚠️ **A quarterly reading is equally
     * available and is the owner's to make** — it is named in the decisions block
     * rather than left for somebody to discover from the code.
     *
     * @return list<array{dueOn: Carbon, amount: Money}>
     */
    public function scheduleFor(PlanSelection $selection, Carbon $firstChargeOn): array
    {
        $spacing = $this->cycleDays();
        $schedule = [];

        foreach ($this->paymentsFor($selection) as $index => $amount) {
            $schedule[] = [
                // `copy()` per payment: Carbon mutates in place, and adding to
                // the same instance three times would produce 30, 60 and 90 days
                // by accident rather than by arithmetic — right here and wrong
                // the moment the loop changes.
                'dueOn' => $firstChargeOn->copy()->addDays($spacing * $index),
                'amount' => $amount,
            ];
        }

        return $schedule;
    }

    /**
     * The last day covered by an annual term that began with this charge.
     *
     * ⚠️ **THIS IS WHAT AN INSTALMENT PLAN BUYS, AND IT IS NOT THE SAME AS THE
     * LAST PAYMENT.** Three payments a cycle apart are collected inside the first
     * quarter; the year they pay for runs the whole way. Storing it is decision
     * 2138's move again — record what we sold, so that a subscription the vendor
     * reports as finished can be told apart from a tenant whose year ran out.
     */
    public function termEndsOn(Carbon $firstChargeOn): Carbon
    {
        return $firstChargeOn->copy()->addYear();
    }

    /**
     * The day the first charge falls — today plus the free trial.
     *
     * ⚠️ **`startOfDay()` BECAUSE AUTHORIZE.NET's `startDate` IS A CALENDAR DAY**
     * and because an instalment schedule is a series of dates rather than
     * instants. Carrying a time would make "does the trial end today" answer
     * differently either side of a request's own clock, on the value that decides
     * whether a row reads `trialing` or `active`.
     */
    public function firstChargeOn(): Carbon
    {
        return Carbon::now()->startOfDay()->addDays($this->trialDays());
    }

    /**
     * ⚠️ **THE ONLY READER OF `billing.trial_days` AND `billing.cycle_days` IN
     * THE BILLING SERVICES, AND THAT IS THE POINT OF PUTTING THEM HERE.**
     * `BillingCheckout` and `AuthorizeNetGateway` each held a private copy of
     * both, with the same refusal written twice — two readers of the key whose
     * value the marketing home also prints, which is decision 505's shape and 518
     * the first time it bit. Both fail rather than falling back to a plausible
     * number: a trial length or a cycle length invented at a call site is a price
     * term invented at a call site.
     */
    public function trialDays(): int
    {
        $days = $this->registry->value('billing.trial_days');

        if (! is_int($days) || $days < 1) {
            throw new RuntimeException(
                'billing.trial_days must be a positive integer; a trial with no length '
                .'cannot be offered honestly.'
            );
        }

        return $days;
    }

    /**
     * ⚠️ The registry's floor is here and the **vendor's** ceiling is not.
     *
     * Authorize.Net expresses a recurring interval in days only between 7 and
     * 365, and that refusal lives at the vendor boundary
     * ({@see AuthorizeNetApi::createSubscription()}) because it is a fact about
     * one gateway rather than about this figure. What is refused here is the
     * value that makes no sense anywhere: a cycle of zero days puts every
     * instalment on the same afternoon.
     */
    public function cycleDays(): int
    {
        $days = $this->registry->value('billing.cycle_days');

        if (! is_int($days) || $days < 1) {
            throw new RuntimeException(
                'billing.cycle_days must be a positive integer; it is the billing interval '
                .'of a live subscription and the spacing between instalments, and cannot be '
                .'guessed.'
            );
        }

        return $days;
    }

    /**
     * The four base-plan figures a price page prints, in one pass.
     *
     * ⛔ **THIS EXISTS FOR THE LCP QUERY BUDGET AND FOR NOTHING ELSE (4337).**
     * Routing the marketing home through this class took it from ten queries to
     * eighteen against a budget of ten, because `cents()` is one SELECT per key
     * and `currency()` is one more beside each. A per-instance memo would have
     * fixed it and is refused — see `cents()`, and 510.
     *
     * ⚠️ **THE BATCH IS A LOCAL, NOT A FIELD, AND THAT DISTINCTION IS THE WHOLE
     * SAFETY ARGUMENT.** It is read once and discarded when this method returns,
     * so it cannot outlive the render it was built for and cannot serve a stale
     * price to a later call on the same object. A price moved between two calls
     * still moves; a price moved *during* one page render was never observable.
     *
     * ⚠️ **THE OFFER IS STILL CONSULTED PER TERM**, through the same memo every
     * other method uses, so this cannot quote a different price from the one a
     * checkout is about to charge.
     *
     * ⚠️ **NOT A SECOND PRICING RULE.** It composes exactly what `unitPriceFor()`
     * and `additionalLocationPriceFor()` compose — offer first, registry second —
     * and a mutation to either of those does not silently leave this one right.
     *
     * @return array{monthly: Money, annual: Money, locationMonthly: Money, locationAnnual: Money}
     *
     * @throws AmbiguousPlanOffer Two offers are live on one term at once.
     */
    public function displayRates(): array
    {
        return $this->rates(withOffers: true);
    }

    /**
     * The same four figures, from the registry alone (4342).
     *
     * ⛔ **THE ONE PERMITTED ANSWER TO {@see AmbiguousPlanOffer}, AND IT IS NOT A
     * TIEBREAK.** 4323 refuses to rank two live offers because either ranking
     * charges somebody a price nobody chose — that stands, and it is why nothing
     * on the charging path may call this. A marketing page is the one caller with
     * an honest alternative: the retail schedule is *ours*, it is in the manifest,
     * `RegistryTest` compares it against `CLAUDE.md` on every run, and printing it
     * is what the page did before `plan_offers` existed. Rule 43's surviving half
     * (3294) asks for graceful degradation rather than a 500 for every visitor.
     *
     * ⚠️ **IT IS PUBLIC AND ITS ONLY CALLER IS `MarketingController`'s `catch`.**
     * Reaching for it anywhere else is quoting a price that may not be the one a
     * checkout is about to charge — the very defect routing that page through this
     * class was meant to close.
     *
     * @return array{monthly: Money, annual: Money, locationMonthly: Money, locationAnnual: Money}
     */
    public function retailDisplayRates(): array
    {
        return $this->rates(withOffers: false);
    }

    /**
     * @return array{monthly: Money, annual: Money, locationMonthly: Money, locationAnnual: Money}
     */
    private function rates(bool $withOffers): array
    {
        $entitlements = $this->registry->entitlementsFor(Plan::Base);
        $currency = $this->currency();

        $figure = function (BillingTerm $term, string $key, bool $addOn) use (
            $entitlements,
            $currency,
            $withOffers,
        ): Money {
            $offer = $withOffers ? $this->offerFor($term) : null;

            if ($offer instanceof PlanOffer) {
                return $addOn ? $offer->additionalLocationPrice() : $offer->price();
            }

            $value = $entitlements[$key] ?? null;

            // A key absent from the batch is either undeclared or withheld, and
            // both must raise — printing `$0` for an unset price is what 502
            // exists to prevent, and the re-ask is what raises.
            return Money::of(
                is_int($value) ? $value : $this->registry->entitlementCents(Plan::Base, $key),
                $currency,
            );
        };

        return [
            'monthly' => $figure(BillingTerm::Monthly, BillingTerm::Monthly->priceKey(), false),
            'annual' => $figure(BillingTerm::Annual, BillingTerm::Annual->priceKey(), false),
            'locationMonthly' => $figure(
                BillingTerm::Monthly,
                BillingTerm::Monthly->additionalLocationKey(),
                true,
            ),
            'locationAnnual' => $figure(
                BillingTerm::Annual,
                BillingTerm::Annual->additionalLocationKey(),
                true,
            ),
        ];
    }

    /**
     * The offer live on this term, resolved once for the life of this instance.
     *
     * ⚠️ **MEMOISED FOR CORRECTNESS RATHER THAN FOR SPEED**, which is the reverse
     * of `PlanPricing`'s memo one file over. A single checkout asks this class for
     * the total, the per-payment schedule, the two component rates and the
     * renewal disclosure — four separate calls — and a founder window closing
     * between two of them would render a page whose halves disagree and then
     * charge a third figure. Resolving once means a purchase is priced by the
     * catalogue as it stood when the request began.
     *
     * ⚠️ **THE MEMO IS THE WHOLE MAP, NOT ONE TERM.** Both terms are loaded in one
     * query, so asking for the annual price does not make the monthly price
     * resolve against a later instant.
     */
    private function offerFor(BillingTerm $term): ?PlanOffer
    {
        $this->liveOffers ??= $this->offers->liveAt();

        return $this->liveOffers[$term->value] ?? null;
    }

    /**
     * Whether any offer is live on any term right now (decision 5198).
     *
     * ⚠️ **IT ANSWERS A QUESTION ABOUT COPY AND NEVER ABOUT A PRICE**, which is
     * why it returns a boolean and not the offer. `/pricing` carries a past-tense
     * sentence about the founder window — *"Founder pricing existed, it closed
     * exactly when we said it would"* — and printing it while the window is open
     * is a page announcing the end of the offer it is selling. Nothing on the
     * charging path may branch on this: what a selection costs is
     * {@see self::priceFor()}'s answer, resolved through the same memo, and a
     * caller that asked this first and then priced by hand would be reimplementing
     * the offer lookup outside the class that owns it.
     *
     * ⚠️ **IT SHARES `offerFor()`'s MEMO**, so a page that quotes four prices and
     * asks this costs one query rather than two, and cannot be told that a window
     * is open by one call and closed by the next.
     *
     * @throws AmbiguousPlanOffer Two offers are live on one term at once.
     */
    public function offerIsLive(): bool
    {
        $this->liveOffers ??= $this->offers->liveAt();

        return $this->liveOffers !== [];
    }

    /**
     * ⚠️ Raises rather than defaulting, on the registry's own rule (502, 505).
     *
     * ⛔ **AND IT IS NOT MEMOISED, WHICH WAS TRIED AND REVERTED INSIDE THIS SLICE
     * (4337).** Routing the marketing home through this class cost the LCP query
     * budget, and the obvious fix was `PlanPricing`'s per-instance batch (519).
     * It reddens `GrandfatheredPricingTest`'s *"a price change still moves what
     * somebody about to buy is quoted"*, which holds one instance across a
     * registry edit — and that test is right. **This is the charging path**: 510
     * keeps the registry uncached because a value written in one place and read
     * through a warm cache in another is a staleness bug no test sees, and here
     * one did. The budget is held by {@see self::displayRates()} instead, which
     * batches within a single call and keeps nothing.
     */
    private function cents(string $key): int
    {
        return $this->registry->entitlementCents(Plan::Base, $key);
    }

    private function currency(): string
    {
        $currency = $this->registry->value('billing.currency');

        if (! is_string($currency) || $currency === '') {
            throw new RuntimeException(
                'billing.currency must be an ISO 4217 code; a price cannot be quoted without one.'
            );
        }

        return $currency;
    }
}
