<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\MessageCostKind;
use App\Exceptions\CostBookUnpriced;
use App\Support\Money;
use InvalidArgumentException;

/**
 * What the internal cost book says a tenant has cost us — **and whether that
 * figure means anything** (decisions 3104, 3105, 3200).
 *
 * ⛔ **A `Money` COULD NOT CARRY THE SECOND HALF, WHICH IS WHY THIS TYPE
 * EXISTS.** `MessageCostLedger::totalCost()` returned a bare `Money` and every
 * unconfigured platform got `Money::of(0, 'USD')` back — indistinguishable from
 * a tenant who has genuinely sent nothing. 3105 names the consequence in terms:
 * *"a cap summing this book reads '$0 spent' for a tenant who has sent thousands
 * of messages and concludes they are comfortably under. A cap built on an empty
 * table does not fail loudly; it passes, every time, for everybody."*
 *
 * So the two facts travel together and the number is not reachable without the
 * flag. {@see self::total()} throws {@see CostBookUnpriced} on an unpriced book
 * rather than answering zero; {@see self::$priced} is what a caller reads first.
 *
 * ## `priced` is a fact about the rate schedule, never about the rows
 *
 * ⚠️ **AN EMPTY BOOK ON A PRICED PLATFORM IS A REAL ZERO AND MUST STILL READ AS
 * ONE.** "Nobody has sent anything" is a true and useful answer; refusing it
 * would trade one indistinguishable pair for another. The question this type
 * settles is only whether the platform *can* price a send — which is
 * {@see MessageRates::isConfigured()} — the method 3105 found in the codebase
 * with no caller at all, written a day ahead of the thing that needed it.
 *
 * ⚠️ **AND IT IS THE SCHEDULE AS IT STANDS NOW, NOT AS IT STOOD WHEN THE ROWS
 * WERE WRITTEN.** A platform whose rates were cleared reports unpriced even
 * though rows exist, because every send since the clearing wrote nothing and the
 * total therefore understates. Understating a spend is the direction that makes
 * a ceiling pass, so it is refused rather than reported.
 *
 * ## Partial pricing travels with the total (3202, 3888)
 *
 * ⚠️ **`priced` IS STILL THE COARSE QUESTION AND STILL TRUE ON ONE RATE OUT OF
 * SIX**, because "can this platform price anything at all" is the question that
 * separates a real zero from an artefact. What changed at 3888 is that the
 * *sharp* question is now answerable from the same object:
 * {@see self::$pricedKinds} is the list, {@see self::unpricedKinds()} is its
 * complement, and {@see self::coversEveryKind()} is the one-line form a screen
 * asks before printing the number as though it were the whole cost.
 *
 * ⛔ **THIS IS NOT A HYPOTHETICAL AND THAT IS WHY IT STOPPED BEING A COMMENT.**
 * Amazon SES publishes its price; Infobip's five are per-account and unknowable
 * (3731). So the configuration this platform can actually reach today is **email
 * priced and SMS, MMS, inbound and the undelivered fee unset** — a book that
 * answers `priced`, renders a figure, and omits the dominant cost, on the one
 * ledger whose job is margin. 3202 recorded the hole in general terms; this
 * branch supplied its first realistic occupant.
 *
 * ⚠️ **WHAT IS STILL NOT CATCHABLE: "GENUINELY FREE".** A kind missing from the
 * list is one nobody entered a rate for, never one a vendor does not charge for,
 * because zero-means-unset has no spelling for the second. A reader is told
 * which kinds are unpriced and has to know what that means; it is not told that
 * the omission is safe.
 *
 * ## The one place millicents become cents (3729)
 *
 * ⚠️ **THE BOOK IS DENOMINATED IN THOUSANDTHS OF A CENT AND `Money` IS NOT**, so
 * a conversion has to happen somewhere and *where* is the whole question.
 * `MessageRates` used to do it per message and floor the result at 1, which
 * overstated a sub-cent SMS by a third and an email — at Amazon SES's order of
 * 10 millicents — by a hundredfold. It happens here instead: once, on the whole
 * sum, after every row has been added at full precision. Three segments at 750
 * are 2,250 millicents and a thousand emails at 10 are 10,000; both are exact,
 * and only the answer is rounded.
 *
 * ⚠️ **AND THE EXACT FIGURE STAYS REACHABLE.** {@see self::totalMillicents()}
 * is what a reconciliation reads and {@see self::total()} is what a screen
 * shows, because a book holding four hundred millicents genuinely rounds to
 * $0.00 and a reader who needs to know it was not empty must be able to ask.
 */
final readonly class CostBookTotal
{
    /** Thousandths of a cent in one cent — a fact about the unit, not a policy. */
    private const int MILLICENTS_PER_CENT = 1000;

    /**
     * @param  bool  $priced  Whether a provider rate exists at all, so the sum
     *                        below is a measure of spend rather than a measure
     *                        of how many rates nobody has entered.
     * @param  list<MessageCostKind>  $pricedKinds  Which of the six kinds have a
     *                                              rate behind them (3888).
     *                                              Empty exactly when
     *                                              `$priced` is false, and
     *                                              shorter than
     *                                              `MessageCostKind::cases()`
     *                                              on a partial book — see
     *                                              {@see self::coversEveryKind()}.
     * @param  int  $millicents  Private on purpose: the only ways to it are
     *                           {@see self::total()} and
     *                           {@see self::totalMillicents()}, and both refuse
     *                           when the book is unpriced. A public property
     *                           would be the unguarded read this class exists to
     *                           remove.
     * @param  string  $currency  ⚠️ **Private for a weaker reason than
     *                            `$millicents` and worth saying so**: it is not
     *                            dangerous to read, it is simply nothing anybody
     *                            has needed, and {@see self::total()} hands it
     *                            back inside the `Money` where it belongs. It is
     *                            validated on every path in, so it is an ISO
     *                            4217 code or the object does not exist.
     */
    private function __construct(
        public bool $priced,
        public array $pricedKinds,
        private int $millicents,
        private string $currency,
    ) {}

    /**
     * The sum of a book the platform can actually price.
     *
     * ⚠️ **Zero is a legitimate argument here** — a priced platform where
     * nothing has been sent — and it is the case the whole type exists to tell
     * apart from {@see self::unpriced()}.
     *
     * ⚠️ **THE UNIT OF `$millicents` IS NOT CENTS AND THE PARAMETER NAME IS THE
     * ONLY WARNING.** It took a `Money` until 3729; a caller still passing cents
     * would understate the book by a thousand rather than failing.
     *
     * @param  list<MessageCostKind>  $pricedKinds  {@see MessageRates::pricedKinds()},
     *                                              refused when empty: a priced
     *                                              book with nothing priced is
     *                                              the incoherent state this
     *                                              type exists to make
     *                                              unspellable.
     */
    public static function priced(int $millicents, string $currency, array $pricedKinds): self
    {
        if ($pricedKinds === []) {
            throw new InvalidArgumentException(
                'A priced cost book has at least one priced kind. An empty list is the '
                .'unpriced state, and it has its own constructor so that the two cannot '
                .'be told apart by reading a flag.'
            );
        }

        // ⚠️ **VALIDATED BY `Money` RATHER THAN TRUSTED, AND SINCE 3887 THE WRITE
        // PATH IS TOO.** This guarantee came free while the parameter was a
        // `Money`; keeping it here covered the read side only, and the row was
        // being labelled from an unvalidated registry string in the same commit
        // that wrote this comment (314–316). `MessageRates::currency()` is where
        // that half now lives.
        return new self(true, $pricedKinds, $millicents, Money::of(0, $currency)->currency);
    }

    /**
     * A book with no rate schedule behind it.
     *
     * Takes the currency it was asked about rather than the total, because there
     * is no total: the currency is only kept so a caller can say *which* book it
     * asked for, and it is validated on the way in for the same reason the
     * priced branch is.
     */
    public static function unpriced(string $currency): self
    {
        return new self(false, [], 0, Money::zero($currency)->currency);
    }

    /**
     * Whether every kind of send this application can make is priced.
     *
     * ⚠️ **THE QUESTION A SCREEN MUST ASK BEFORE PRINTING THE TOTAL AS "WHAT WE
     * SPENT"** (3888). False means the number is real as far as it goes and
     * excludes the *future* sends of whatever {@see self::unpricedKinds()} names
     * — which, on the configuration this platform can reach today, is likely to
     * be every SMS it sends from now on.
     *
     * ⚠️ **"EXCLUDES" IS ABOUT SENDS, NOT ABOUT ROWS** (3893). Read
     * {@see self::unpricedKinds()} before drawing a conclusion about what the
     * total contains: a named kind may still have rows inside it from before its
     * rate was cleared.
     */
    public function coversEveryKind(): bool
    {
        return count($this->pricedKinds) === count(MessageCostKind::cases());
    }

    /**
     * The kinds the rate schedule is silent about **today**.
     *
     * ⚠️ **SILENT, NOT ZERO.** A kind with no rate writes no row at all
     * ({@see MessageRates::costFor()} answers null), so the sends it is making
     * *now* are absent from the sum rather than present at nothing — which is
     * why a reader needs the names rather than a count.
     *
     * ⛔ **AND THIS IS A FACT ABOUT THE SCHEDULE, NEVER ABOUT THE ROWS** (3893).
     * `MessageCostLedger::totalCost()` sums every row in the book with **no kind
     * predicate at all**, so a kind that was priced, wrote rows, and then had its
     * rate cleared is named here *while its recorded spend stays in the total*.
     * This docblock read as though the sum and this list agreed; they never did.
     *
     * ⛔ **FILTERING THE SUM TO {@see self::$pricedKinds} WOULD BE THE WRONG FIX
     * AND WAS REFUSED** (3893). It would drop real recorded spend and
     * **understate** the book — the direction this class's own header refuses in
     * terms (*"understating a spend is the direction that makes a ceiling pass,
     * so it is refused rather than reported"*). A total that shrinks when an
     * operator clears a rate is worse than a list that needs one sentence of
     * explanation, and this is that sentence.
     *
     * @return list<MessageCostKind>
     */
    public function unpricedKinds(): array
    {
        return array_values(array_filter(
            MessageCostKind::cases(),
            fn (MessageCostKind $kind): bool => ! in_array($kind, $this->pricedKinds, true),
        ));
    }

    /**
     * What this tenant has cost us, to the nearest cent.
     *
     * ⚠️ **ROUNDED HALF UP AND NO LONGER FLOORED AT ONE.** A total under half a
     * cent answers `$0.00`, which is honest at this altitude and is not the
     * ambiguity 3105 is about: {@see self::$priced} has already said whether the
     * book means anything, and {@see self::totalMillicents()} holds the exact
     * figure for anybody who needs it.
     *
     * ⚠️ **`intdiv` AND AN EXPLICIT REMAINDER RATHER THAN `round()`**, because
     * `round()` returns a float and this codebase has no path by which a float
     * reaches a money column — `Money`'s own docblock: *"there is no float in
     * this file and there is no way to get one out of it."* The sign comes out
     * first so a negative book — a carrier credit — rounds away from zero the
     * same way a positive one does rather than toward it.
     *
     * @throws CostBookUnpriced when no provider rate is configured — read
     *                          {@see self::$priced} and render the unconfigured
     *                          state instead of catching this.
     */
    public function total(): Money
    {
        $millicents = $this->totalMillicents();

        $sign = $millicents < 0 ? -1 : 1;
        $magnitude = abs($millicents);

        $cents = intdiv($magnitude, self::MILLICENTS_PER_CENT);

        if ($magnitude % self::MILLICENTS_PER_CENT >= intdiv(self::MILLICENTS_PER_CENT, 2)) {
            $cents++;
        }

        return Money::of($sign * $cents, $this->currency);
    }

    /**
     * What this tenant has cost us, exactly.
     *
     * ⚠️ **THE UNIT IS THOUSANDTHS OF A CENT AND THE METHOD NAME IS THE ONLY
     * WARNING A CALLER GETS.** Rendering it where cents were expected overstates
     * by a thousand. It exists because rounding to cents is lossy on a book
     * whose rows are individually sub-cent, and a reconciliation against a
     * vendor invoice needs the figure that was actually recorded rather than the
     * one a screen showed.
     *
     * @throws CostBookUnpriced for the same reason {@see self::total()} does.
     */
    public function totalMillicents(): int
    {
        if (! $this->priced) {
            throw CostBookUnpriced::noRateIsConfigured();
        }

        return $this->millicents;
    }
}
