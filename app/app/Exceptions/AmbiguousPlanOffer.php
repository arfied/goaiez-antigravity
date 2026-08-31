<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Two offers are live on one billing term at once (T176 P1, decisions 4323, 4342).
 *
 * ⚠️ **IT IS A NAMED TYPE RATHER THAN A BARE `RuntimeException` SO THAT ONE
 * CALLER CAN DEGRADE AND THE REST CANNOT.** 4323 refuses to rank two live offers
 * and that refusal stands: at quote time there is no honest answer, because
 * "the newest" and "the cheapest" both charge somebody a price nobody chose. A
 * marketing page has an honest answer — the retail schedule — and `29` §2 rule
 * 43's surviving half (graceful degradation, never hard-fail, 3294) says it
 * should give that rather than return 500 to every visitor on the site.
 *
 * ⛔ **CATCHING THIS TO PICK ONE OF THE TWO PRICES IS THE THING IT EXISTS TO
 * PREVENT.** The only permitted response is the registry schedule — a figure
 * that is stated in the manifest and compared against `CLAUDE.md` on every run —
 * never one of the colliding rows.
 *
 * It still extends `RuntimeException`, so the tests and callers written against
 * that type before it had a name are unaffected.
 */
final class AmbiguousPlanOffer extends RuntimeException
{
    public static function between(string $term, string $first, string $second): self
    {
        return new self(
            "Two offers are live on the {$term} term at once ('{$first}' and '{$second}'), and "
            .'there is no correct way to choose between them. Close one window before the other '
            .'opens — a tiebreak here would charge somebody a price nobody selected.'
        );
    }
}
