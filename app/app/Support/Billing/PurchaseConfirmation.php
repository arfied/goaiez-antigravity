<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Exceptions\CreditPurchaseRefused;
use App\Services\Billing\CreditPurchases;
use App\Support\Money;
use Illuminate\Support\Carbon;

/**
 * Proof that a person was shown an amount and agreed to be charged it.
 *
 * ⛔ **CONFIRM APPLIES TO BUYING CREDIT, BY NAME.** `CLAUDE.md` reserves CONFIRM
 * for exactly three things and *"anything that spends money"* is the second of
 * them. A top-up is the plainest instance of it in the product: the tenant's card
 * is charged, now, for an amount.
 *
 * ## Why a type and not a boolean, and not an `if`
 *
 * {@see App\Support\Trials\TrialGrantAuthorization} is the precedent and
 * `PlaceConfirmation::confirm()`'s literal `true $confirmed` is the precedent
 * behind that: an `if ($confirmed)` inside a method is a line somebody can delete
 * with no test necessarily noticing. Here
 * {@see CreditPurchases::open()} takes one of these as a
 * **required, undefaulted parameter**, so there is no path to a charge that did
 * not construct one — and constructing one means naming an actor, a wording and
 * an amount.
 *
 * ⛔ **AND IT CARRIES THE AMOUNT, WHICH IS THE HALF THAT ACTUALLY BITES.** A
 * confirmation that only says "yes" is a confirmation of nothing in particular:
 * the price could move between the screen and the charge, and the tenant would
 * have agreed to a figure they never saw. So the amount shown is part of the
 * record and `intend()` refuses a purchase whose SKU price differs from it —
 * which is `CLAUDE.md`'s *"never bill by surprise"* expressed as a comparison
 * rather than as an intention.
 *
 * ⚠️ **IT IS A RECORD, NOT A CHECKBOX** — the shape `ImportAttestation` uses and
 * every consent record in this codebase uses: who, when, and the wording they
 * were shown. All three are persisted on `credit_purchases`, because the question
 * a chargeback asks is *what did you show them*, and a boolean cannot answer it.
 *
 * ⚠️ **IT IS THE CONFIRM FOR AN *ARRANGEMENT* TOO, AND THIS PARAGRAPH SAID THE
 * OPPOSITE.** It read *"automatic top-up is not built … nothing here is reused
 * for it"* — true when it was written, and 2505's shape now that it is built.
 * {@see App\Services\Billing\AutoTopUps::agree()} takes one of these as a
 * required parameter and copies its four fields onto the arrangement, and
 * `AutoTopUps::confirmationFor()` rebuilds one from that row for every charge
 * made under it. 2064 and 3296 want automatic top-up confirmed **once, as an
 * arrangement, rather than per charge**, and reusing this type is how a charge
 * made months later with nobody watching still reaches `CreditPurchases::open()`
 * carrying the wording the tenant actually read.
 *
 * ⚠️ **WHAT IT IS STILL NOT.** It is not a *fresh* agreement at each automatic
 * charge. One constructed at 3am from figures nobody was shown would satisfy
 * every signature here and answer none of the questions this record exists to
 * answer.
 */
final readonly class PurchaseConfirmation
{
    /**
     * @param  string  $actor  Who agreed. A user identifier or a staff actor
     *                         string, matching the `$actor` argument every service
     *                         in this codebase takes — never a foreign key, so the
     *                         record survives the user row being deleted.
     * @param  Money  $amountShown  The figure that was on the screen they agreed
     *                              to. Compared against the SKU's price before
     *                              anything is charged.
     * @param  string  $wording  What they were shown, verbatim.
     */
    private function __construct(
        public string $actor,
        public Money $amountShown,
        public string $wording,
        public Carbon $confirmedAt,
    ) {}

    /**
     * The only constructor, and it refuses the empty forms.
     *
     * ⚠️ **AN EMPTY WORDING IS REFUSED RATHER THAN STORED.** A confirmation whose
     * wording is `''` is a boolean wearing this class's clothes, and it would
     * satisfy every signature in the funder while answering none of the questions
     * this record exists to answer.
     *
     * @throws CreditPurchaseRefused
     */
    public static function given(string $actor, Money $amountShown, string $wording): self
    {
        $actor = trim($actor);
        $wording = trim($wording);

        if ($actor === '') {
            throw CreditPurchaseRefused::because(
                'A confirmation with no actor confirms nothing. Somebody agreed to be '
                .'charged, and the record of who is the whole point of asking.'
            );
        }

        if (mb_strlen($wording) < 10) {
            throw CreditPurchaseRefused::because(
                'Record the wording the person was shown, in a sentence. A confirmation '
                .'that does not say what was agreed to cannot answer the question a '
                .'chargeback asks.'
            );
        }

        if ($amountShown->minorUnits <= 0) {
            throw CreditPurchaseRefused::because(
                'A confirmation is agreement to be charged an amount, so the amount '
                .'shown must be positive. Nothing here gives credit away.'
            );
        }

        return new self($actor, $amountShown, $wording, Carbon::now());
    }
}
