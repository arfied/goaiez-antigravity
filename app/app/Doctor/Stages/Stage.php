<?php

declare(strict_types=1);

namespace App\Doctor\Stages;

/**
 * A doctor stage.
 *
 * ⭐ EVERY VIOLATION CARRIES ITS FIX. `§262`'s N-262-03: a check that only
 * refuses gets disabled; one that proposes the fix gets maintained.
 *
 * ⛔ AND NO CHECK IS ANCHORED ON MARKUP. `N-262-05` — anchor on CONTENT, never
 * on `|`, `#` or bold. Nine markup over-matches were recorded across windows
 * before this rule existed; the checks below read code and annotations only.
 *
 * @return list<array{where:string, what:string, fix:string}>
 */
interface Stage
{
    /** @return list<array{where:string, what:string, fix:string}> */
    public function run(): array;
}
