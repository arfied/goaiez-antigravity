<?php

declare(strict_types=1);

namespace App\Services\Activity;

use App\Models\ActivityFeedItem;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * The one place an owner's activity feed is read (`29` §2 rule 42, `28` §85).
 *
 * ⛔ **`activity_feed` WAS A WRITE-ONLY TABLE AND THAT IS WHY THIS EXISTS**
 * (6073). Thirty-one services and jobs file rows into it through
 * `ActivityService`, and **nothing in `app/` rendered one** — no screen, no
 * route, no component. `29` §2's *every automated action → activity feed* was
 * satisfied at the writer and nowhere else, which is decision 620's inversion
 * at the scale of the owner's entire history: the record this product keeps of
 * what it did on somebody's behalf existed, grew daily, and could not be
 * opened.
 *
 * ⚠️ **A SERVICE RATHER THAN A QUERY IN THE COMPONENT**, on `MessageLog`'s
 * reasoning: this is the one place a tenant's history is read as a list, and
 * the next screen that wants a slice of it grows a method here rather than a
 * second query whose tenant scope and ordering drift from this one's.
 *
 * ⚠️ **ORDERED BY `id`, NEVER BY `created_at`.** `activity_feed.created_at` is
 * nullable — Laravel's own default, and this table has no `updated_at` at all —
 * and Postgres sorts NULL *first* on a DESC ordering, so a timestamp ordering
 * would put every undated row above everything that actually happened.
 * `ConventionsTest`'s *no descending order relies on Postgres putting NULLs
 * first* lint exempts `id` for exactly this reason, and the table is
 * append-only, so its ids **are** insertion order. `AuthorByline` argues the
 * identical point two files away and its first draft got it wrong.
 *
 * ⚠️ **THE TENANT BOUNDARY IS THE GLOBAL SCOPE ON `ActivityFeedItem`, WITH RLS
 * UNDER IT** — two layers, neither of which this file may reach around. There
 * is no by-id read here and no route parameter anywhere in the slice, so there
 * is no door through which another tenant's row could be named.
 */
final class ActivityFeed
{
    /** How many entries one page of the feed shows. */
    public const int PER_PAGE = 25;

    public function __construct(private readonly DefaultsRegistry $defaults) {}

    public function perPage(): int
    {
        return $this->defaults->int('activity.feed.per_page');
    }

    /**
     * The tenant's history, newest first.
     *
     * ⚠️ **`with('location')` IS NOT OPTIONAL POLISH.** Every row on the screen
     * names the place it happened to, and a page is twenty-five rows — so
     * without it this is twenty-six queries on the one owner screen built to be
     * scrolled.
     *
     * @return LengthAwarePaginator<int, ActivityFeedItem>
     */
    public function page(): LengthAwarePaginator
    {
        Tenancy::idOrFail();

        return ActivityFeedItem::query()
            ->with('location')
            ->orderByDesc('id')
            ->paginate($this->perPage());
    }

    /**
     * Whether anything has ever been filed for this tenant.
     *
     * Distinguishes *"nothing has happened yet"* from *"this page of your
     * history is empty"*, which one empty list otherwise collapses into — and
     * the first is the state every tenant is in on their first day.
     */
    public function isEmpty(): bool
    {
        Tenancy::idOrFail();

        return ! ActivityFeedItem::query()->exists();
    }

    /**
     * When this happened, in the reader's words.
     *
     * ⚠️ **A ROW WITH NO DATE SAYS SO RATHER THAN READING AS "just now".**
     * `created_at` is nullable on this table and `ActivityService::record()`
     * always fills it, so an undated row is one written by something that was
     * not the supported writer — and `diffForHumans()` on a null would either
     * throw or print the current moment, which dates an event that has no date.
     * `MessageLog`'s screen takes the same fork.
     */
    public static function when(ActivityFeedItem $item): string
    {
        $at = $item->created_at;

        return $at instanceof Carbon ? $at->diffForHumans() : 'Not dated';
    }
}
