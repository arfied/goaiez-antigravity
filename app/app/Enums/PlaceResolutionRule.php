<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which row of `24` §1.2.1's ladder produced a `place_id`.
 *
 * Recorded on the audit row at confirmation (`24` §1.2.3 requires "which row
 * resolved it"), and it earns that place: when a `place_id` later turns out to
 * be wrong, the rule that produced it is the difference between "the owner
 * pasted a link to the wrong branch" and "our text search picked the wrong
 * candidate". Those have different fixes and only one of them is our fault.
 *
 * THE ORDER MATTERS AND THE COMMON CASE IS NOT THE FIRST ONE. `24` §1.2.1 is
 * blunt about it: "Rows 3–5 are the common case, not the exception. A modern
 * Maps share link usually contains no `placeid=` at all... Any parser built only
 * for the boss's rows 1–2 and `cid=` will fail for most owners." The ladder runs
 * top-down and stops at the first row that yields an id, but the row that
 * usually yields it is the last one.
 */
enum PlaceResolutionRule: string
{
    /** Row 1 — `placeid=` or `place_id=` in the query string. No network call. */
    case PlaceIdParameter = 'place_id_parameter';

    /**
     * Row 2 — a short link, resolved to its destination and re-run.
     *
     * Never a terminal rule: it produces a URL, not an id. The rule recorded is
     * whichever row matched the resolved URL.
     */
    case ShortLink = 'short_link';

    /** Row 3 — the `!1s0x…:0x…` feature-id token, whose second half is the CID. */
    case FeatureId = 'feature_id';

    /** Row 4 — `cid=<decimal>` in the query string. */
    case CidParameter = 'cid_parameter';

    /**
     * Row 5 — Places Text Search on the name and coordinates in the URL.
     *
     * The only rule that costs money, and the one rows 3 and 4 always continue
     * into: `24` §1.2.1 notes there is no official CID → `place_id` conversion
     * and a CID is not accepted by the `writereview` endpoint.
     */
    case TextSearch = 'text_search';

    /**
     * Whether this rule needs a metered Places call.
     */
    public function costsMoney(): bool
    {
        return $this === self::TextSearch;
    }
}
