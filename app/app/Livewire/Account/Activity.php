<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Services\Activity\ActivityFeed;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Everything that has happened to this business, newest first — `28` §85's
 * *Activity* entry in the Normal navigation, and decision 6073's whole subject.
 *
 * ⛔ **THE DOOR ON A TABLE THAT HAD NONE, AND THE LARGEST ONE OF THESE SO FAR.**
 * `activity_feed` has been collecting rows since Stage 0 and **nothing in
 * `app/` rendered one** (6073). Thirty-one services and jobs write to it, `29`
 * §2 rule 42 requires every automated action to reach it within sixty seconds,
 * and the person the record is kept for could not open it. `Messages`, `Home`
 * and `Credit` each shipped for the same reason; this one is the whole history
 * rather than one store's slice of it.
 *
 * ## Normal, and Normal only
 *
 * ⚠️ **NO FILTERS AND NO SEARCH, AND THAT IS `28` §106'S OWN RULING** rather
 * than this slice being unfinished: *"Activity feed | ✅ | ✅ + filters/search |
 * ✅ read-only"* — plain in Normal, filtered in Advanced, read-only in Ops.
 * Advanced is deferred whole rather than half-built, which is `Messages`'
 * precedent one screen over, and it agrees with `CLAUDE.md`'s first
 * tie-breaker.
 *
 * ⚠️ **AND NOTHING ON IT IS PRESSABLE.** There is no action here at all: the
 * feed is append-only at the model layer, so there is nothing to acknowledge,
 * dismiss, resolve or delete, and a control that appeared to do any of those
 * would be lying about a table whose whole property is that history cannot be
 * rewritten. What an owner does about a row is done on the screen that owns the
 * thing — the undo lives on *What we changed on your website*, the reconnect on
 * *Google reviews* — and `OwnerNavTest` refuses a hand-written link between
 * owner screens, so this page points at none of them by name.
 *
 * ## Livewire hygiene
 *
 * ⚠️ **THERE IS NO PROPERTY TO LOCK, AND THAT IS THE STRONGEST VERSION OF
 * `#[Locked]`.** The one client-settable value on this screen is the page
 * number, which `WithPagination` owns and which names no record: every row
 * comes out of a tenant-scoped read with no id in it, so there is nothing a
 * client could set that would change *which* rows this screen is about. A test
 * pins that — the shape of the `Admin\AccountAudit::$businessId` defect, refused
 * by construction rather than by an attribute.
 */
#[Layout('components.account.layout')]
final class Activity extends Component
{
    use WithPagination;

    public function render(ActivityFeed $feed): View
    {
        // Refused rather than resolved when there is no tenant, for the reason
        // `Home`, `Settings` and `Messages` give: internal staff belong to no
        // business by design (`28` §9.1), so a signed-in support agent typing
        // this URL is the ordinary way to arrive with nothing resolved, and
        // letting `Tenancy::idOrFail()` reach the renderer is a 500 that reads
        // as our page being broken.
        abort_if(Tenancy::id() === null, 403);

        return view('livewire.account.activity', [
            'entries' => $feed->page(),
            'nothingYet' => $feed->isEmpty(),
        ]);
    }
}
