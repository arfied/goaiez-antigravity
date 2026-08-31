<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use RuntimeException;

/**
 * One received request, as bytes, plus the id the collector assigned it.
 *
 * ⚠️ **ONE RECEIPT IS ONE HTTP REQUEST, NOT ONE EVENT**, and that follows from
 * §5.2 rather than from convenience: L0 holds *"the full received payload"*, and
 * the pixel's payload is a **batch** — the bundle's `flush()` posts
 * one body carrying a `k`, a `device` block, `referrer_*`, a `utm` block, a
 * `click_id` block and an `events` array of up to twenty. Splitting that into one
 * line per event at the archive boundary would mean L0 stored something nobody
 * ever sent, and the envelope would have to be copied onto each line or lost.
 * The batch is exploded in the derivation, where §5.1 puts it (*"L1 CONFORMED.
 * Validated, typed, enriched, deduplicated"*).
 *
 * ⚠️ **`payload` IS NOT PARSED HERE AND MUST NOT BE.** The moment this class
 * decodes it, an archive becomes a transformation and the thing replay goes back
 * to stops existing. Concretely: the pixel sends floats — `record('vital', {
 * metric: 'CLS', value: Math.round(cls * 1000) / 1000 })` — and a decode/encode
 * round trip renders them through PHP's `serialize_precision`, an ini setting. A
 * malformed payload is therefore archivable and only becomes a reject
 * downstream, which is also what makes the archive faithful to a broken client.
 *
 * ⚠️ **THE PIXEL BUNDLE IS DELIBERATELY NOT NAMED BY PATH ANYWHERE IN THIS
 * DIRECTORY**, and that is not squeamishness: `PixelTest`'s fourth tripwire
 * (4571) fails the build when any file under `app/`, `routes/` or
 * `resources/views/` mentions it, because that is how a *delivery* would look —
 * and it caught this docblock. The lint cannot tell a reference from a mention,
 * which is the right trade for a bundle whose delivery must stay auditable.
 *
 * ⛔ **THAT LAST CLAUSE READ "while its collector does not exist" UNTIL
 * 2026-08-27 AND THE COLLECTOR HAS EXISTED SINCE 2026-08-18** — corrected by the
 * wave-39 integrator, reported by wave 39 lane D at decision 10760 while it was
 * fixing the same stale sentence in the bundle's own source. **This was the
 * THIRD live copy**, and the shape matters more than the sentence: that file had
 * already corrected the claim for itself four paragraphs above the copy still
 * carrying it, so a reader who checked one paragraph got the truth and a reader
 * who checked the other got the opposite, **in the same file**.
 * ⚠️ **AND THE FIRST DRAFT OF THIS VERY CORRECTION NAMED THAT FILE BY PATH AND
 * REDDENED THE TRIPWIRE ABOVE** — which is the paragraph above's own sentence,
 * proved on the paragraph below it. **Cite it by description, never by path.** ⚠️ **The lint's reason is untouched and is not the stale
 * part** — a mention here still reads exactly like a delivery, and that trade
 * stands whatever the collector's state.
 */
final readonly class L0Receipt
{
    public function __construct(
        public string $receiptId,
        public string $payload,
    ) {
        if ($receiptId === '') {
            throw new RuntimeException(
                'An L0 receipt with no id cannot be traced back from an L1 row, which is what '
                .'`l0_path` and §5.3\'s lineage requirement exist for.',
            );
        }
    }
}
