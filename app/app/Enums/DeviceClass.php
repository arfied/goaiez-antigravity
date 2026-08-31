<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The warehouse's device-class vocabulary — L1's `device_type` column and
 * every mart grain derived from it.
 *
 * ⚠️ **THE TABLE NAMES ARE DELIBERATELY NOT WRITTEN OUT ANYWHERE IN THIS FILE.**
 * `WarehouseTest`'s *"nothing outside the warehouse writes to a derived layer"*
 * lint matches them as literals across `app/`, and its own docblock says its
 * reach is names rather than reachability. Adding an enum to its exemption list
 * to buy a tidier sentence would widen a lint for a comment.
 *
 * `GOAIEZ_PIXEL_MASTER_BUILD` §10 asks for *"device-class bucketing"* and names
 * no widths; `28` §4.3 asks for RUM vitals *"p75 by device class"*. The widths
 * themselves stay in [[\App\Services\Warehouse\L1Derivation]], which is where
 * they have always been declared as ours rather than the specification's
 * (decision 4866) — this enum is the **vocabulary**, not the boundary.
 *
 * ⛔ **THESE FOUR STRINGS ARE STORED IN A DERIVED LAYER, SO CHANGING ONE
 * CHANGES EVERY PAST REPLAY'S OUTPUT.** They are a constant for the same reason
 * `L1Derivation::PHONE_MAX_VIEWPORT` is: a value a person can edit is a value
 * yesterday's rebuild cannot reproduce.
 *
 * ⚠️ **[[ClickDeviceClass]] CARRIES THE SAME FOUR VALUES AND IS DELIBERATELY
 * NOT MERGED WITH THIS.** That one buckets a short-link click from a
 * User-Agent string and is stored on `short_link_clicks`; this one buckets a
 * pixel visitor from a viewport width and is stored in the warehouse. The
 * vocabularies agree today by coincidence rather than by construction, and
 * merging them would make a change made for one domain silently rewrite the
 * other's stored values — including rows a replay must reproduce byte for
 * byte. Two enums that happen to agree is the cheaper mistake.
 */
enum DeviceClass: string
{
    case Phone = 'phone';
    case Tablet = 'tablet';
    case Desktop = 'desktop';

    /**
     * No usable viewport. ⚠️ **Its own answer, never folded into `Phone`** —
     * `28` §4.3's whole point is that phones are measured separately, so a
     * visitor whose viewport never arrived must not be counted as one.
     */
    case Unknown = 'unknown';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
