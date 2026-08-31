<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who a URL was announced to — `indexing_submissions.engine`.
 *
 * ⛔ **THE COLUMN'S OWN COMMENT SAYS `google|bing` AND `bing` IS DELIBERATELY
 * NOT A CASE HERE.** IndexNow's protocol requires that *"submitted URLs will be
 * automatically shared with all other participating search engines"*
 * (`indexnow.org/documentation`, fetched 2026-08-20), and its FAQ says *"You may
 * submit your request to only one of the following participating endpoints …
 * your submission will be shared across all IndexNow-enabled search engines"*.
 * There are seven endpoints and they are equivalent; choosing Bing's and
 * recording the row as `bing` would be a false statement about where the URL
 * went — it went to all of them, whichever one we posted to.
 *
 * ⚠️ **SO DECISION 48's *"IndexNow → Bing/Yandex/Seznam/Naver"* IS NOT A LIST OF
 * FOUR CALLS TO MAKE** (5681). It is a list of who hears about it, and it is
 * also two engines short today: Amazon and Yep have since joined, and there is
 * now a shared endpoint that did not exist when 48 was written.
 */
enum IndexingEngine: string
{
    /**
     * Google, reached by exactly one of the three methods Google documents —
     * never by a Search Console API write, which decisions 1083 and 5480 close.
     */
    case Google = 'google';

    /**
     * The IndexNow consortium, as one destination.
     *
     * ⚠️ **ONE CASE FOR SIX COMPANIES, AND THE SUBPROCESSOR INVENTORY IS WHERE
     * THAT IS SPELT OUT** rather than here — the protocol's sharing rule means a
     * single submission is a disclosure to every participant, which is a fact
     * about the relationship rather than about this column.
     */
    case IndexNow = 'indexnow';

    /**
     * Which search engines this is, in words an operator recognises.
     *
     * ⚠️ **"BING AND FIVE OTHERS" RATHER THAN "INDEXNOW"** — somebody asking
     * *"did we tell Bing?"* should not have to know the name of a protocol to
     * find out, and naming Bing alone would understate who received it.
     * `Admin\LocationSettings` builds one panel row per case from this, so a
     * seventh participant joining is one edit here and not two.
     */
    public function audience(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::IndexNow => 'Bing, Yandex, Seznam, Naver, Amazon and Yep',
        };
    }
}
