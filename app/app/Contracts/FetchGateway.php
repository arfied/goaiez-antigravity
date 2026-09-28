<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\FetchTier;
use App\Services\Fetch\FetchResult;

/**
 * The only way this application fetches a page.
 *
 * `40` Part 6: "One gateway for every outbound page fetch (Brain crawls,
 * tenant-site audits, directory adapters, review-link validation, competitor
 * public pages where permitted, microsite health probes). No module fetches HTML
 * on its own — build-failing import test."
 *
 * That import lint is in tests/Feature/Architecture/OutboundHttpTest.php and it is the reason this interface
 * exists in row 2 rather than in the row `40` imagined. `40` Part 8 assigns the
 * gateway to a build row "1c" in the `30`–`40` pack's own numbering, which does
 * not map onto `29` §11.2's 26 rows — so nothing ever scheduled it, while the
 * lint that assumes it exists was already specified (`BUILD-PLAN` §4.4). Row 2's
 * NAP quick-scan is the first outbound page fetch in the system. Building the
 * gateway here was the alternative to writing code the lint would later fail.
 *
 * ONLY F0 IS IMPLEMENTED. The policy gate covers every tier; the ladder does
 * not. Asking for F1 or F2 raises rather than silently falling back — see
 * FetchTier.
 *
 * NEVER THROWS ON A REFUSAL OR A BLOCK. Both are ordinary outcomes with an
 * honest name (`40` §6.4 turns them into different sentences for the owner), so
 * the result type carries them rather than the exception path. Only a
 * programming error — an unimplemented tier, an unknown source — raises.
 */
interface FetchGateway
{
    /**
     * Fetch a URL on behalf of a registered source, or say honestly why not.
     *
     * The source key decides policy: a `guided_only` source is refused at every
     * tier, a killed source is refused outright, robots is consulted, and the
     * per-source rate budget applies. Every call writes a `fetch_attempts` row,
     * including the ones that never open a socket.
     */
    public function fetch(string $sourceKey, string $url, FetchTier $tier = FetchTier::F0): FetchResult;

    /**
     * Whether a fetch would be permitted, without making one.
     *
     * For callers that need to decide what to show before deciding what to do —
     * a check that cannot run must say so rather than fabricating, and knowing
     * in advance is how it says so without a wasted request.
     */
    public function permits(string $sourceKey, FetchTier $tier = FetchTier::F0): bool;
}
