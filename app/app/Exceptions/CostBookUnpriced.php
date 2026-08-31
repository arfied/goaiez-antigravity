<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when code asks the internal cost book for a spend figure on a platform
 * where no carrier rate has been entered (decisions 3104, 3105).
 *
 * ⛔ **THE THING THIS REFUSES IS A ZERO, AND THE ZERO IS THE DEFECT.** All five
 * carrier rates seed to `0`, `0` means unset rather than free, and an unset rate
 * short-circuits the *write* — so `message_cost_entries` is **empty rather than
 * zero-valued** on a fresh install. 3105 says what that costs, verbatim: *"'no
 * rate is configured' and 'no messages were sent' both render as no rows, so a
 * cap summing this book reads '$0 spent' for a tenant who has sent thousands of
 * messages and concludes they are comfortably under. A cap built on an empty
 * table does not fail loudly; it passes, every time, for everybody."*
 *
 * This is the loud failure. `WithheldRegistryValue`'s posture, one book over: a
 * figure that may not be guessed answers with an exception naming the decision,
 * not with a plausible number.
 *
 * ## If you are here because this threw
 *
 * The fix is not a fallback and it is not `?? 0`. Ask `CostBookTotal::$priced`
 * first and render the unconfigured state — the answer to "what has this tenant
 * cost us" is *"nobody has told us what a message costs yet"*, which is a
 * different sentence from a zero, and it is the whole reason that type exists.
 *
 * ⚠️ **THAT CLASS IS NAMED IN PROSE AND NOT WITH A `{@see}`, WHICH IS
 * MECHANICAL RATHER THAN STYLISTIC.** Pint's `fully_qualified_strict_types`
 * promotes a `{@see}` into a real `use` statement, and it did exactly that on
 * the first `composer lint` of this slice — leaving an exception importing the
 * service that throws it, which already imports this. `MessageRates` records the
 * same fixer doing the same thing, and this is the fourth time this codebase has
 * written it down.
 *
 * The other way out is an operator entering the rates on Ops → Platform →
 * Settings. ⛔ **Not this lane's to guess**: Infobip prices per account, the
 * figure is in no artefact this codebase can read, and `CLAUDE.md` records four
 * separate burns from writing a plausible vendor number from memory.
 */
final class CostBookUnpriced extends RuntimeException
{
    public static function noRateIsConfigured(): self
    {
        return new self(
            'The internal cost book has no carrier rate behind it, so its total is not a '
            .'measure of spend. Every rate seeds to 0, 0 means unset rather than free, and an '
            .'unset rate writes no cost row at all — so an empty book and a silent tenant look '
            .'identical (decisions 3104, 3105). Read CostBookTotal::$priced and say so, rather '
            .'than reporting a zero.'
        );
    }
}
