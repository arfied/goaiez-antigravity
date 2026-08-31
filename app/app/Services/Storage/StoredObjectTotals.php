<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Enums\StoredObjectKind;

/**
 * One kind's line on a tenant's storage footprint.
 *
 * ⛔ **`$unmeasured` IS A SEPARATE NUMBER AND NOT PART OF `$bytes`, WHICH IS THE
 * ONLY HONEST WAY TO REPORT THIS.** An object whose row names a path but carries
 * no size is one this application knows it stored and does not know the size of
 * — every `campaign_recipients` and `knowledge_sources` row written before
 * 4762's migration is one. Folding those into the total as zero would make the
 * oldest accounts, which have the most stored, read as the emptiest; dropping
 * them from `$objects` would hide them entirely. So they are counted, named, and
 * kept out of the byte figure, and the Ops command prints the caveat whenever
 * this is not zero.
 */
final readonly class StoredObjectTotals
{
    public function __construct(
        public StoredObjectKind $kind,
        /** How many objects this application believes it has stored of this kind. */
        public int $objects,
        /** The bytes of the objects whose size is recorded. Never includes $unmeasured. */
        public int $bytes,
        /** Objects with a path and no recorded size. See the class docblock. */
        public int $unmeasured,
    ) {}
}
