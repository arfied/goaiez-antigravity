<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Services\Widgets\WidgetInstalls;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One sighting of a tenant's review widget, working, on one of their websites.
 *
 * ⚠️ **A ROW MEANS THE BUNDLE RAN AND WE SERVED IT** — not that a snippet is
 * pasted somewhere. It is written when the installed widget fetches its feed
 * (3080), which is the only event that proves the whole chain: the line is on
 * the page, the browser executed it, the key resolved, and the origin was on the
 * tenant's own allowlist. ⛔ **A crawl of the tenant's site would have reported
 * 2950's ten-day defect as healthy** — the snippet was there and the feed
 * answered 403 to every browser in the world. This cannot make that mistake,
 * because what it observes is the answer rather than the question.
 *
 * ⚠️ **AND THE CONVERSE IS A FALSE NEGATIVE THAT MUST NOT BE CALLED "NOT
 * INSTALLED"** (3084). A correct install on a page nobody has visited since it
 * was pasted has no row here. The screen says *"Not seen yet"* and tells the
 * owner that opening their own website closes it; it never tells somebody who
 * did the work correctly that they failed.
 *
 * ⛔ **NOTHING ON THIS ROW IS ABOUT A PERSON.** The creating migration carries
 * the full reasoning and a lint carries the enforcement: which feed, which of
 * the tenant's own hosts, first seen, last seen. No IP, no user agent, no
 * referrer, no path, no identifier, and deliberately no counter (3085, 3087).
 *
 * Written and read only through {@see WidgetInstalls}, which is where the
 * allowlist re-check, the write throttle and the staleness window live.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $plugin_id
 * @property string $host
 * @property CarbonImmutable $first_seen_at
 * @property CarbonImmutable $last_seen_at
 */
final class WidgetInstall extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * ⚠️ **`host` IS NOT FILLABLE, AND THAT IS THE POINT OF THE COLUMN.**
     *
     * It may only ever hold a host the tenant already named in
     * `plugins.allowed_domains`, normalised by the parser that matched it
     * (3090, 3093). `WidgetInstalls::record()` writes it explicitly on the
     * insert; leaving it mass-assignable would let some future caller put a raw
     * `Origin` header from an anonymous public request into it, which is the one
     * way this table could start holding a string a stranger chose.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_seen_at',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
        ];
    }
}
