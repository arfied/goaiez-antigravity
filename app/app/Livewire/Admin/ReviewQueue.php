<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\AuditLogEntry;
use App\Models\Business;
use App\Models\Location;
use App\Models\Review;
use App\Services\AuditService;
use App\Services\Reviews\ReviewDisplay;
use App\Support\Admin\AdminAccess;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LogicException;
use Masmerise\Toaster\Toaster;

/**
 * The display queue (`17` FPR-05) — the "else one-tap" half.
 *
 * ⚠️ **DELIBERATELY SMALL, AND DELIBERATELY BUILT ANYWAY.** DASH-02's Reviews
 * Inbox is the real screen and is Sprint 9: filters, badge counts, triage
 * threads, one-thumb operation on a phone. This is a list and two buttons.
 *
 * It exists because the alternative was shipping `ReviewDisplay` with no caller
 * — and a service written, documented and uncalled is the single most repeated
 * defect in this codebase: `Business::provision()` (272), `autopilot_settings`
 * (377), `feedback_pages`, `review_destinations`, and `plugins` in this very
 * slice. Every one of them looked complete and did nothing. A queue with no way
 * to approve would leave every non-5-star review at `pending` forever with no
 * surface anywhere saying so.
 *
 * ⛔ **AND THAT SENTENCE WAS TRUE ANYWAY FOR AS LONG AS THIS SCREEN HAS EXISTED,
 * BY A ROUTE ITS AUTHOR DID NOT CONSIDER** (9236). `ReviewQueue` answered
 * **`TenantNotResolved` — a 500 —** to every member of platform staff, because
 * it read a tenant-scoped `Location` on a screen whose whole audience owns no
 * business and therefore has no tenant. Having a caller in `app/` is not the
 * same claim as that caller being reachable, and the caller was the only surface
 * in the product that can move a 1–4-star first-party review off `pending`
 * (`ReviewRouter` auto-approves 5-star alone).
 *
 * ## Named, not listed, and read inside that account's tenancy
 *
 * {@see AutomationRuns}'s pattern and 5730–5734's reasoning. `businesses`
 * carries two RLS policies and neither admits platform staff, so the runtime
 * role cannot enumerate accounts at all (569); an operator names one,
 * `Tenancy::actingAs()` sets `app.business_id` to it, and nothing else becomes
 * visible. A wrong number and a number belonging to nobody are deliberately
 * indistinguishable.
 *
 * ⚠️ **THE ROUTE NAMES A LOCATION AND A LOCATION CANNOT NAME ITS ACCOUNT HERE.**
 * `locations` is `ENABLE`+`FORCE` row-level security, so there is no read that
 * turns a path segment into the tenant it belongs to without already being
 * inside one — which is why the account is asked for rather than derived, and
 * why the location is checked *inside* the named tenancy. A location belonging
 * to somebody else is answered as *"that account has no location N"*, the same
 * sentence a number belonging to nobody gets.
 *
 * ⛔ **NO ACCOUNT NAMED IS A RENDERED STATE AND RUNS NO QUERY AT ALL** (5733).
 * Not an unscoped query, not the ambient tenant, and not `TenantNotResolved`
 * thrown at somebody who has just signed in. The ambient tenant is the quiet
 * wrong answer this screen used to give: `ResolveTenant` hands decision 621's
 * `super_admin`-who-also-owns-a-business *their own* tenant, so the one
 * population that could render this screen at all was shown **their own
 * location's reviews under somebody else's name**.
 *
 * ⚠️ **THE READ IS RECORDED IN THE ACCOUNT IT OPENED** — `business.viewed_by_staff`,
 * `surface: admin.review-queue`, and the location id in its metadata (5734).
 * A review row carries a customer's name and their words about a business.
 */
final class ReviewQueue extends Component
{
    /**
     * The location this screen was opened on, straight from the path.
     *
     * ⚠️ **`#[Locked]` BECAUSE IT NAMES A RECORD AND `mount()` IS THE ONLY THING
     * THAT MAY SET IT** — `Admin\TenantLocations`' reasoning. Unlocked it
     * arrives in the update payload, so a crafted request could move an
     * already-named account's queue to a different location of that account
     * while the `business.viewed_by_staff` row filed at lookup still names the
     * one the operator opened. The tenancy holds either way; the *record of
     * what was read* would not.
     */
    #[Locked]
    public int $locationId;

    /** What the operator typed. An account number; see {@see self::resolve()}. */
    public string $reference = '';

    /**
     * The account they have named, or null.
     *
     * ⚠️ **`#[Locked]` BECAUSE {@see self::resolve()} IS THE ONLY THING THAT MAY
     * SET IT, AND THE AUDIT ROW IS WRITTEN THERE.** Without the attribute this
     * is an ordinary public property that arrives in the update payload, so
     * anybody who can reach this component could point it at any account and
     * read that tenant's pending reviews — customers' names and their words —
     * with `resolve()` never called and nothing recorded anywhere.
     */
    #[Locked]
    public ?int $businessId = null;

    /**
     * The account's name, for the operator's own screen.
     *
     * ⚠️ **DELIBERATELY NOT `#[Locked]`, WHICH IS 5793's LINE RATHER THAN AN
     * OVERSIGHT.** `AccountAudit` locks its copy because that screen is an
     * attestation about who read what and a screenshot of it is handed to an
     * auditor; `AutomationRuns` and `TenantLocations` leave theirs open because
     * they are not. This one is not either — every row it decides is audited on
     * the review, in the tenant's own log, keyed on ids this string cannot move.
     */
    public string $businessName = '';

    public function mount(int $location): void
    {
        // Repeated on the component rather than left to the route's `can:`
        // middleware — decision 630: a route-gate test passes while `mount()` is
        // wide open, because `can:` refuses during route matching and the
        // component never runs.
        $this->authorize(AdminAccess::GATE);

        // ⛔ **NO QUERY, AND THAT IS THE FIX** (5733). This line used to be
        // `Location::query()->findOrFail($location)`, whose comment argued that
        // the scope *"makes a foreign id a 404 rather than a leak"*. That is
        // true of a reader who has a tenant and silent about one who has none,
        // where the same line is a 500 — `CLAUDE.md`'s *a phrase that names two
        // different outcomes is worse than a wrong one*, in the file. The
        // segment is kept as typed and resolved inside a named account below.
        $this->locationId = $location;
    }

    /**
     * Name an account, and file the fact that we did.
     *
     * A miss says so plainly rather than 404ing the screen: the operator has
     * typed a number, and "no account with that number" is the answer to that,
     * where a 404 reads as the screen itself being broken.
     *
     * ⚠️ One entry per resolved lookup, never per render (5734). Livewire
     * re-renders on every property update, so recording the read path would file
     * a row per keystroke and make the log unreadable — which is its own kind of
     * unaudited. A miss writes nothing: there is no tenant to file it under, and
     * refusing a number discloses nothing about an account that does not exist.
     *
     * ⚠️ **THE ROW IS FILED BEFORE THE LOCATION IS CHECKED, ON PURPOSE.** An
     * operator who names a real account has opened it and this application has
     * read inside it, whether or not the location in the path turns out to be
     * theirs. Filing only on the happy path would leave the probe — name an
     * account, learn whether it owns location N — as the one read this screen
     * does not record.
     *
     * ⚠️ **AND IT CAN THROW, WHICH IS CORRECT RATHER THAN OVERSIGHT.** The audit
     * write is not wrapped and `$businessId` is set after it, so an unwritable
     * `audit_log` refuses the whole lookup instead of showing the account
     * unrecorded (`PhiTenants::lookUp()`'s reasoning).
     */
    public function resolve(AuditService $audit): void
    {
        $this->authorize(AdminAccess::GATE);

        $this->reset(['businessId', 'businessName']);
        $this->resetErrorBag();

        $reference = trim($this->reference);

        if ($reference === '' || ! ctype_digit($reference)) {
            $this->addError('reference', 'Enter the account number from the ticket.');

            return;
        }

        $id = (int) $reference;

        $business = Tenancy::actingAs(
            $id,
            // Its own tenant context, because `businesses` is FORCE ROW LEVEL
            // SECURITY on its own id: outside it this returns nothing whatever
            // the id is.
            fn (): ?Business => Business::query()->whereKey($id)->first(),
        );

        if (! $business instanceof Business) {
            $this->addError('reference', 'No account with that number.');

            return;
        }

        Tenancy::actingAs(
            $id,
            fn (): AuditLogEntry => $audit->record(
                'business.viewed_by_staff',
                $this->actor(),
                $business,
                ['surface' => 'admin.review-queue', 'location_id' => $this->locationId],
            ),
        );

        $known = Tenancy::actingAs(
            $id,
            fn (): bool => Location::query()->whereKey($this->locationId)->exists(),
        );

        if (! $known) {
            $this->addError('reference', 'That account has no location '.$this->locationId.'.');

            return;
        }

        $this->businessId = $id;
        $this->businessName = (string) $business->name;
    }

    public function clearAccount(): void
    {
        $this->reset(['businessId', 'businessName', 'reference']);
    }

    public function approve(int $reviewId, ReviewDisplay $display): void
    {
        $this->decide($reviewId, $display, approve: true);
    }

    public function reject(int $reviewId, ReviewDisplay $display): void
    {
        $this->decide($reviewId, $display, approve: false);
    }

    /**
     * The queue, read inside the named account's tenancy.
     *
     * ⚠️ **EVERYTHING IS MATERIALISED BEFORE THE CLOSURE ENDS**, which is the
     * trap `AutomationRuns::runs()` names in its own `finally`: a builder or an
     * unloaded relation handed to the view outlives the tenancy it was built in.
     * `pendingFor()` returns models already fetched and nothing this view prints
     * is a relation.
     *
     * ⚠️ **A LOCATION THAT NO LONGER RESOLVES FALLS BACK TO THE INVITATION**
     * rather than throwing — `TenantLocations::render()`'s shape. It is reachable
     * by an account being erased between the lookup and this render.
     */
    public function render(ReviewDisplay $display): View
    {
        if ($this->businessId === null) {
            return view('livewire.admin.review-queue', [
                'location' => null,
                'reviews' => new Collection,
            ]);
        }

        /** @var array{location: ?Location, reviews: Collection<int, Review>} $state */
        $state = Tenancy::actingAs($this->businessId, function () use ($display): array {
            $location = Location::query()->whereKey($this->locationId)->first();

            return [
                'location' => $location,
                'reviews' => $location instanceof Location
                    ? $display->pendingFor($location)
                    : new Collection,
            ];
        });

        return view('livewire.admin.review-queue', $state);
    }

    /**
     * Apply one decision and say what happened.
     *
     * ⚠️ THE REVIEW IS RE-READ UNDER SCOPE FROM AN ID THE CLIENT SENT. A
     * Livewire action argument is request input — it arrives in the update
     * payload and nothing about it is trustworthy. The scope of the named
     * account turns a foreign id into a 404, and `ReviewDisplay` independently
     * re-checks the tenant before writing, which is the guard that survives this
     * component being replaced by DASH-02.
     *
     * ⚠️ THE SERVICE'S REFUSALS ARE SHOWN, NOT SWALLOWED. `ReviewDisplay`
     * throws on a Google review and on a flagged one, and both messages explain
     * a rule rather than a failure. A generic "something went wrong" here would
     * turn `29` §2 rule 1 into a mystery.
     *
     * ⛔ **REACHED WITH NO ACCOUNT NAMED IT THROWS, AND LOUD IS THE POINT** —
     * `AutomationRuns::query()`'s reasoning. The buttons only exist on the
     * rendered queue, so this state is a hand-posted update rather than
     * something a person can click, and writing a review decision under whatever
     * tenant happened to be in context is the quiet wrong answer.
     */
    private function decide(int $reviewId, ReviewDisplay $display, bool $approve): void
    {
        $this->authorize(AdminAccess::GATE);

        $businessId = $this->businessId ?? throw new LogicException(
            'ReviewQueue::decide() was reached with no account named. Nothing here '
            .'may approve or reject a review without saying whose it is.'
        );

        try {
            Tenancy::actingAs($businessId, function () use ($reviewId, $display, $approve): void {
                $review = Review::query()
                    ->where('location_id', $this->locationId)
                    ->findOrFail($reviewId);

                $approve
                    ? $display->approve($review, $this->actor())
                    : $display->reject($review, $this->actor());
            });
        } catch (InvalidArgumentException $e) {
            Toaster::error($e->getMessage());

            return;
        }

        // Outcome language, and the verb survives the flow (`29` §2 rule 47,
        // `22`): Approve becomes Approved. No customer content in a toast —
        // it is not the audit record and carries no personal data (decision
        // 104), so it names what happened and nothing about who wrote it.
        Toaster::success($approve ? 'Review approved' : 'Review held back');
    }

    /**
     * Who made the decision, for `approved_by` and the audit row.
     *
     * An actor *label*, not a user id — the column's own migration comment says
     * so, because autopilot approves most reviews and a foreign key would have
     * nothing to point at for those.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'admin' : 'user:'.$id;
    }
}
