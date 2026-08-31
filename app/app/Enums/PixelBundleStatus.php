<?php

declare(strict_types=1);

namespace App\Enums;

use App\Console\Commands\WatchPixelCanary;
use App\Services\Pixel\PixelDelivery;

/**
 * Where one published pixel bundle stands in `/p.js`'s delivery rotation —
 * `GOAIEZ_PIXEL_MASTER_BUILD.md` §10's Delivery paragraph.
 *
 * A `string` column cast to this enum, never a database enum — CLAUDE.md
 * §Critical rules and the convention test that enforces it.
 *
 * ⚠️ **AT MOST ONE ROW MAY HOLD {@see self::Active} AND AT MOST ONE MAY HOLD
 * {@see self::Canary} AT ONCE**, enforced by a partial unique index in the
 * creating migration rather than only in {@see PixelDelivery}
 * — the same discipline `pixel_keys` uses for one key per business: a check two
 * concurrent writers can both pass is not a check.
 */
enum PixelBundleStatus: string
{
    /**
     * Serving 100% of `/p.js` traffic that is not diverted to a live canary.
     */
    case Active = 'active';

    /**
     * Serving the small slice of `/p.js` traffic `pixel.canary_percent_bp`
     * names, for `pixel.canary_window_minutes` from {@see
     * \App\Models\PixelBundleVersion::$canary_started_at}. Never served
     * directly by URL — only `/p.js`'s own coin flip may choose it.
     */
    case Canary = 'canary';

    /**
     * A canary that tripped {@see WatchPixelCanary}'s
     * regression threshold, or a version `pixel:rollback` moved away from.
     * Never served again by either delivery route's pointer — only reachable,
     * for whoever needs the exact bytes back, at its own immutable
     * `/v/<sha>/p.js`.
     */
    case RolledBack = 'rolled_back';

    /**
     * A version that was promoted to {@see self::Active} and has since been
     * superseded by a later publish. Same reachability as {@see self::RolledBack}.
     */
    case Retired = 'retired';
}
