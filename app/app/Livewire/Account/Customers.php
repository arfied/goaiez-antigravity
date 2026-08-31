<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Enums\ContactView;
use App\Services\Consent\ConsentService;
use App\Services\Crm\CustomerDirectory;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The owner's customers (`34` §1.1).
 *
 * ⚠️ **THE DOOR ON A STORE THAT HAS HAD ROWS IN IT SINCE ROW 3.** Every feedback
 * submission creates or matches a `customers` row, the attested import (545)
 * writes them in bulk, and until this screen there was nowhere in the product to
 * see one. See `CustomerDirectory` for what §1.1 asks for that is not here and
 * why each piece is missing rather than cut.
 *
 * ## Normal only, and the empty state is the interesting one
 *
 * §1.1's empty state is *"an invitation into Import"*, and this is the first
 * screen in the application whose empty state has somewhere to send people — the
 * import screen already exists. What it must never do is send somebody there
 * because their *search* missed, which is why `CustomerDirectory::isEmpty()`
 * exists as a separate question.
 */
#[Layout('components.account.layout')]
final class Customers extends Component
{
    use WithPagination;

    /**
     * ⚠️ IN THE URL DELIBERATELY, AND IT IS THE ONE THING IN IT. Decision 396's
     * rule is that the *tenant* is never in the URL, not that nothing is: a
     * search an owner can bookmark and send to their own staff is the point of
     * a list screen, and the tenant still comes from the session.
     */
    #[Url(as: 'q', except: '')]
    public string $search = '';

    /**
     * §1.1's "Needs follow-up" chip — open triage or an open follow-up. In the
     * URL for the same reason the search is: a filtered list an owner can
     * bookmark, with the tenant still coming from the session.
     */
    #[Url(as: 'follow-up', except: false)]
    public bool $needsFollowUp = false;

    /**
     * Which of the three rooms is showing (`44` §8's archived view, and 1540's
     * Recently deleted). Each door renders only while its room has somebody in
     * it — a link to an always-empty room is clutter, and a hidden contact with
     * no door back is a trap (1502).
     *
     * ⚠️ **A STRING IN THE URL AND AN ENUM EVERYWHERE ELSE, ON PURPOSE.** This is
     * the one property a stranger can type a value into, so it is parsed rather
     * than trusted: `view()` below falls back to the default room on anything
     * unrecognised. Binding `ContactView` directly would make a hand-edited
     * query string a 500 on a screen an owner reaches by bookmark.
     */
    #[Url(as: 'view', except: '')]
    public string $view = '';

    /**
     * The room being shown, or the default one if the URL says something this
     * application does not recognise.
     */
    public function view(): ContactView
    {
        return ContactView::tryFrom($this->view) ?? ContactView::Active;
    }

    /**
     * A new search starts at page one.
     *
     * Without this, an owner on page four of 200 contacts who types a name is
     * shown page four of the two that matched — which renders as an empty list,
     * and reads as "no results" for a search that found some.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** The chip changes the result set, so it restarts the pages too. */
    public function updatedNeedsFollowUp(): void
    {
        $this->resetPage();
    }

    /** So does moving between rooms. */
    public function updatedView(): void
    {
        $this->resetPage();
    }

    public function render(CustomerDirectory $directory, ConsentService $consent): View
    {
        // Refused rather than resolved when there is no tenant, for the reason
        // `Messages` and `Settings` give: internal staff belong to no business
        // by design (`28` §9.1), so a signed-in support agent typing this URL is
        // the ordinary way to arrive here with nothing resolved.
        abort_if(Tenancy::id() === null, 403);

        $customers = $directory->page($this->search, $this->needsFollowUp, $this->view());

        return view('livewire.account.customers', [
            'customers' => $customers,
            'view' => $this->view(),
            // ⚠️ Batched, never proofFor() per row — one page is twenty-five
            // contacts and two chokepointed tables (624's rule met before the
            // N+1 makes widening a chokepoint look reasonable).
            'consentBadges' => $consent->badgesFor(collect($customers->items())),
            'noneAtAll' => $directory->isEmpty(),
            'hasArchived' => $directory->hasArchived(),
            'hasRecentlyDeleted' => $directory->hasRecentlyDeleted(),
        ]);
    }
}
