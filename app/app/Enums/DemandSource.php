<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where the evidence came from that somebody is actually asking this question —
 * doc `16` §15.3: *"answers a question with demonstrated real demand (support
 * inbox, GSC query, or keyword volume)"*.
 *
 * ⛔ **THE GATE REQUIRES EVIDENCE TO EXIST AND CARRY A COUNT ABOVE ZERO. IT DOES
 * NOT REQUIRE A PARTICULAR VOLUME, AND THE MISSING NUMBER IS DELIBERATE.** *"How
 * many people asking is enough?"* is a product judgement nobody has made — it is
 * not in `16`, `29` or `33` — and `CLAUDE.md` forbids inventing one. A seeded
 * floor pulled out of the air would read as policy for ever after. What the gate
 * can say honestly today is the difference `16` actually draws: a page answering
 * a question somebody asked, versus a page answering nothing.
 */
enum DemandSource: string
{
    /** A real customer question that arrived in the tenant's own inbox. */
    case SupportInbox = 'support_inbox';

    /**
     * A query the tenant's site was already found for, read from Search Console.
     *
     * ⚠️ **`GoogleSearchConsoleClient` IS READ-ONLY BY CONSTRUCTION** (1083) and
     * this case takes nothing away from that: it names where a figure came from,
     * and the figure arrives with the candidate rather than being fetched here.
     */
    case SearchConsoleQuery = 'search_console_query';

    /** A keyword volume from whatever research produced the plan. */
    case KeywordVolume = 'keyword_volume';
}
