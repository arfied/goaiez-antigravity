<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Consent\IdentifierHashEpochs;

/**
 * Whether this install can still read the hashes it has stored.
 *
 * ⛔ **FOUR STATES, AND THE WHOLE POINT IS THAT `Rotated` IS NOT `Current`.**
 * Before 8080 there were two — a stored hash matched or it did not — and *"this
 * person never opted out"* and *"every hash on this platform was written under a
 * key we no longer have"* were the same answer. `29` §2's consent rules make
 * that the one direction this system may not fail in.
 *
 * @see IdentifierHashEpochs
 */
enum IdentifierHashEpochStatus: string
{
    /**
     * No live epoch **and** no stored identifier hash anywhere — the three
     * suppression stores are empty.
     *
     * ⚠️ **A FRESH CHECKOUT, AND IT MUST NOT CRY WOLF.** There is nothing to be
     * unable to read, so nothing is refused. The first carrier STOP or register
     * import records an epoch and this state is left for ever.
     *
     * ⛔ **THE SECOND HALF OF THAT SENTENCE IS THE CORRECTION OF 2026-08-22, AND
     * WITHOUT IT THIS CASE WAS THE HAZARD IT WAS BUILT TO DETECT** (8183). This
     * state used to be derived from the epoch table **alone** — no live row
     * meant *fresh install*, whatever else the schema held. So an install that
     * upgraded onto this guard **with suppression rows already in it** read as
     * fresh and refused nothing, which is the original fail-open in full; and a
     * supported command sequence could empty the live set again afterwards. An
     * empty register is only *readable* because it is empty, so this case now
     * asks whether it is.
     */
    case Unrecorded = 'unrecorded';

    /**
     * ⛔ **Durable identifier hashes are stored and no live epoch claims them.**
     *
     * Nothing in this application can tell whether the key now in `.env` is the
     * one those rows were written under — the honest answer is *we do not
     * know*, and the cost of guessing wrong in the readable direction is texting
     * somebody who sent STOP. So it is **not readable** and every send is
     * refused until a person says which key wrote them.
     *
     * ⚠️ **AND THE APPLICATION MAY NOT ANSWER IT FOR THEM.** Recording the
     * current fingerprint here — a backfill in the migration, or a reader that
     * files what it happens to find — is 8082's fail-open with a different
     * caller: it would assert *these rows are readable* on evidence nobody has,
     * and the assertion would then read `Current` for ever while the rows sat
     * inert. The way out is `consent:hash-epoch --adopt`, which is attributed
     * because the fact it records is one only a person can supply.
     */
    case Unattributed = 'unattributed';

    /**
     * Every epoch that still describes live data is this install's own key.
     */
    case Current = 'current';

    /**
     * ⛔ **A live epoch names a key this install no longer hashes with.**
     *
     * Every stored `value_hash` written under it is unmatchable: `opt_outs`
     * refuses nobody, the DNC, litigator and reassigned-number registers refuse
     * nobody, and no `suppression_lifts` row can be paired with the refusal it
     * clears. The suppression registers cannot answer, so nothing may read their
     * silence as *"no"*.
     */
    case Rotated = 'rotated';

    /**
     * Whether a stored identifier hash can still be compared against a fresh
     * one.
     *
     * ⚠️ `Unrecorded` IS READABLE, AND THAT IS NOT A CONCESSION. A store with no
     * rows in it answers every lookup correctly; the question this predicate
     * asks is whether a *stored* hash is comparable, and there is none.
     *
     * ⛔ **`Unattributed` IS NOT READABLE AND IT IS THE ONE ARM WHERE THE ANSWER
     * IS A JUDGEMENT RATHER THAN A FACT.** There are stored hashes and no record
     * of the key behind them, so *readable* and *unreadable* are both guesses.
     * One of the two guesses sends a message to somebody who asked us to stop
     * and the other refuses a message that would have been fine, and `29` §2
     * makes that choice for us.
     */
    public function isReadable(): bool
    {
        return $this === self::Current || $this === self::Unrecorded;
    }
}
