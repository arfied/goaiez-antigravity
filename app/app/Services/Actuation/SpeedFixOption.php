<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\SpeedFix;
use App\Enums\SpeedFixRefusal;

/**
 * One of `28` §4.1's seven fixes, against one site, with the reason it is not
 * being applied if it is not.
 *
 * ⛔ **A REFUSED FIX IS AN ENTRY, NEVER AN OMISSION** (1222). A plan that
 * listed only what could be done would make *"we cannot do this here"* and *"we
 * never considered it"* the same output — and on this path the first is
 * something an owner is owed an explanation for, because the answer for all
 * seven today is the same one and it names a thing they could change.
 */
final readonly class SpeedFixOption
{
    public function __construct(
        public SpeedFix $fix,
        public ?SpeedFixRefusal $refusal = null,
    ) {}

    public function isApplicable(): bool
    {
        return $this->refusal === null;
    }
}
