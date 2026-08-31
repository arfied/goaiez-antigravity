<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\PlacesSku;
use Exception;

/**
 * The daily Places budget declined to spend more today.
 *
 * NOT AN ERROR. This is the safeguard working — decision 193's ceiling, failing
 * closed, on a free unauthenticated endpoint that spends real money
 * (`BUILD-PLAN` §6). Nothing is broken and nothing needs fixing.
 *
 * WHAT A CALLER MUST DO WITH IT. Catch it and degrade. `29` §11.2 row 2's gate
 * and BUILD-PLAN §2.5.3 both require that budget exhaustion "degrades, never
 * throws at the visitor", and §2.5.3 requires that a check which cannot run
 * "says so rather than fabricating". So: no score, a plain sentence, a way to
 * try again later — never a 500, and never a finding invented to fill the gap.
 *
 * It carries the SKU rather than a message about money, because the useful
 * operational question is which call was refused, and because the daily figure
 * belongs in the ops view rather than in an exception a log might ship somewhere.
 */
final class PlacesBudgetExhausted extends Exception
{
    private function __construct(
        public readonly PlacesSku $sku,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forSku(PlacesSku $sku): self
    {
        return new self(
            $sku,
            "The daily Places budget declined a {$sku->value} call. This is the cost cap "
            .'working, not a failure — degrade the check and say so plainly.',
        );
    }
}
