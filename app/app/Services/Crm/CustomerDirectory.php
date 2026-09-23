<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Enums\ContactView;
use App\Enums\ReviewStatus;
use App\Models\Customer;
use App\Models\Review;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one place the owner's own customer list is read (`34` §1.1).
 *
 * ⚠️ **THE TENANT CRM HAS NEVER HAD A DOOR.** `customers` is the oldest table in
 * this schema that carries a person's name, it has had writers since row 3 slice
 * C (`/f/{slug}` creates a contact on every feedback submission) and since the
 * attested list import (545), and the only `/account/customers*` route in this
 * application was that import screen. So a business owner could put customers in
 * and never look at one. This is decision 620's inversion again — a missing
 * *reader* shows up as nothing at all rather than as an empty screen — and it is
 * the second time this worktree has had to build a door for a store that already
 * had rows in it (`MessageLog`, 940, was the first).
 *
 * ## Narrower than §1.1, and each narrowing is structural rather than a cut
 *
 * **No lifetime value column.** §1.1 says *"when POS/pay data exists, else
 * hidden — never a $0 column"*, and it does not exist: `value_to_date_cents`
 * has a default of `0`, no writer anywhere in `app/`, and no payment integration
 * behind it. Rendering it would tell every owner that every customer they have
 * is worth nothing, which is the exact failure the spec's own parenthesis
 * forbids.
 *
 * **The "needs follow-up" chip filters open triage and open follow-ups, and
 * still not missed calls.** §1.1's definition is *open triage/missed call*;
 * voice is Stage 2, so that half has no source and the chip is named for what
 * it actually filters. The follow-ups half arrived when `crm_tasks` gained its
 * writer (`BUILD-PLAN` §2.9.3 slice 2) — read through `CrmTasks`, because the
 * table sits behind a chokepoint lint and this directory never touches it.
 *
 * **No bulk actions and no saved views.** §1.1 puts both in Advanced, and doc
 * `44` §4 routes bulk sending through the broadcast composer, which needs row
 * 4's sending layer (551).
 *
 * ## Why a service rather than a query in the component
 *
 * `MessageLog`'s reason, which has held twice: the next screen wanting a
 * customer list grows its own query, and its tenant scope, its ordering and its
 * search semantics then drift from this one's. The ordering here is the part
 * that would drift silently — see `page()`.
 */
final class CustomerDirectory
{
    /** How many contacts one page of the list shows. */
    public const int PER_PAGE = 25;

    public function __construct(
        private readonly CrmTasks $tasks,
        private readonly DefaultsRegistry $defaults,
    ) {}

    /**
     * The tenant's contacts, most recently active first.
     *
     * ⚠️ **`last_activity_at DESC NULLS LAST` IS WRITTEN OUT, AND `->latest()`
     * WOULD HAVE BEEN WRONG.** §1.1 makes last activity the default sort, the
     * column is nullable, and Postgres sorts NULL *first* on a DESC ordering —
     * so `latest('last_activity_at')` puts every contact nobody has ever
     * interacted with above every contact who was in yesterday. That is the
     * defect decision 289's neighbourhood hit three times in one slice and the
     * reason an `ArchitectureTest` lint permits no column but `id` on
     * `latest`/`oldest`/`orderByDesc`. Here the ordering is a product
     * requirement rather than a convenience, so it is spelled out in SQL that
     * says NULLS LAST out loud, with `id` beneath it as the tiebreak that
     * actually runs.
     *
     * @return LengthAwarePaginator<int, Customer>
     */
    public function page(
        string $search = '',
        bool $needsFollowUp = false,
        ContactView $view = ContactView::Active,
    ): LengthAwarePaginator {
        Tenancy::idOrFail();

        return $this->applyView($this->search(Customer::query(), $search), $view)
            // ⚠️ **NOT A FILTER — A MERGED-AWAY ROW LEAVES EVERY VIEW OF THIS
            // LIST, THE ARCHIVED ONE INCLUDED.** Archive below is a place a
            // contact can be; this is the contact no longer existing separately.
            // It is what makes `34` §7's "zero pickers" true, because every
            // place an owner chooses a contact runs through this method — and
            // the send half deliberately does NOT live here, for the reason the
            // archive comment gives (1327, 398's caller).
            ->whereNull('merged_into_id')
            ->when($needsFollowUp, fn (Builder $query): Builder => $this->needsFollowUp($query))
            ->orderByRaw('last_activity_at DESC NULLS LAST')
            ->orderByDesc('id')
            ->paginate($this->defaults->int('crm.directory.per_page'));
    }

    /**
     * Which room each of the three views is.
     *
     * ⚠️ **THE ARCHIVED VIEW EXCLUDES A CONTACT WHO WAS ARCHIVED AND THEN
     * DELETED, AND THAT IS DELIBERATE** (1541). Both columns are set on such a
     * row — they are independent states, not one field — so a naive archived
     * filter would list them in two rooms at once, offering two restores a
     * screen apart that mean different things. Delete is the later and stronger
     * statement, so it wins the listing; restoring from Recently deleted
     * returns them to the archived room, which is where they were.
     *
     * ⚠️ **RecentlyDeleted IS BOUNDED BY THE WINDOW, NOT MERELY BY THE COLUMN.**
     * A contact leaves this view at day seven while the row stays exactly where
     * it is — that is what makes the door's "renders only while occupied" rule
     * (1502) mean something here rather than opening a room that only ever
     * grows. The bound is expressed in SQL here and as a PHP guard in
     * `CustomerEditor::undelete()`; a test drives both at day 6 and day 8 and
     * asserts they agree, on 1531's rule.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    private function applyView(Builder $query, ContactView $view): Builder
    {
        return match ($view) {
            ContactView::Active => $query
                ->whereNull('archived_at')
                ->whereNull('deleted_at'),
            ContactView::Archived => $query
                ->whereNotNull('archived_at')
                ->whereNull('deleted_at'),
            ContactView::RecentlyDeleted => $query
                ->where('deleted_at', '>', $this->restorableSince()),
        };
    }

    /**
     * The moment a delete stops being undoable — the editor's, deliberately
     * not a second copy of the arithmetic. What this class expresses
     * differently is the *comparison*, in SQL rather than in PHP; two copies of
     * the threshold itself would just be two windows waiting to drift.
     */
    private function restorableSince(): CarbonImmutable
    {
        return CustomerEditor::restorableSince();
    }

    /**
     * Whether any contact is archived — what decides if the list offers the
     * archived view at all. A door to an always-empty room is clutter; a
     * hidden contact with no door back is a trap.
     *
     * Excludes the deleted for `applyView()`'s reason: they are listed in the
     * other room, and a door to a room whose only occupant is shown elsewhere
     * is the empty room this rule exists to refuse.
     */
    public function hasArchived(): bool
    {
        Tenancy::idOrFail();

        return Customer::query()
            ->whereNull('merged_into_id')
            ->whereNotNull('archived_at')
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Whether any contact is inside the seven days a delete can be undone in —
     * what decides whether the Recently deleted door renders at all (1502's
     * rule, which archive established and this inherits).
     *
     * ⚠️ **IT ASKS THE WINDOW, NOT THE COLUMN.** A tenant whose last delete was
     * eight days ago has a tombstone and nothing to do about it, so a door
     * keyed on `deleted_at IS NOT NULL` would render forever after the first
     * delete a tenant ever made, opening a room with an action nobody can take.
     */
    public function hasRecentlyDeleted(): bool
    {
        Tenancy::idOrFail();

        return Customer::query()
            ->whereNull('merged_into_id')
            ->where('deleted_at', '>', $this->restorableSince())
            ->exists();
    }

    /**
     * §1.1's "Needs follow-up" chip: an open triage episode or an open
     * follow-up. Missed calls are the third leg of §1.1's definition and have
     * no source until voice exists (Stage 2) — the chip's label names what it
     * filters, not the spec's full definition.
     *
     * Both legs are subqueries, so the tenant boundary holds twice over: the
     * global scopes on `Review` and `CrmTask` apply inside them (`toSql()` is
     * passthru and runs `applyScopes()` first — decision 365's note), and RLS
     * sits beneath.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    private function needsFollowUp(Builder $query): Builder
    {
        return $query->where(function (Builder $builder): void {
            $builder
                ->whereIn('id', $this->tasks->customersWithOpenTasks())
                ->orWhereIn('id', Review::query()
                    ->where('status', ReviewStatus::InTriage)
                    ->select('customer_id'));
        });
    }

    /**
     * One contact, or a 404.
     *
     * ⚠️ **THE TENANT BOUNDARY HERE IS THE GLOBAL SCOPE, AND RLS BENEATH IT.**
     * `Customer` uses `BelongsToTenant`, so a foreign id finds nothing and
     * `findOrFail` raises — the same 404 an id that does not exist produces,
     * which is deliberate: distinguishing "not yours" from "not there" tells an
     * attacker which ids are real.
     *
     * ⚠️ **A MERGED-AWAY CONTACT STILL RESOLVES HERE, AND AN ARCHIVED ONE
     * ALREADY DID.** Both are hidden from `page()` and neither is hidden from a
     * bookmark: a 404 on a contact somebody merged last week reads as data loss
     * rather than as tidying, and the profile it renders says where the history
     * went. It is also the door the undo is reachable through when the survivor
     * is not the row somebody opened.
     *
     * ⚠️ **A DELETED CONTACT RESOLVES HERE TOO, AND `34` §1.2'S OWN NOUN IS THE
     * ARGUMENT** (1542). It asks for a *tombstone* with restore, and a tombstone
     * is a thing you can see — a 404 is not one. So the profile renders the
     * marker rather than refusing: with a restore inside the seven days, and
     * with the same marker and no button after them, which is the only honest
     * answer to a bookmark somebody kept. The refusal that matters is on the
     * *write* — `CustomerEditor::undelete()` checks the window itself, so the
     * page having no button and the service refusing are two independent facts
     * (391's rule).
     *
     * ⚠️ It is resolved here rather than by implicit route binding **because the
     * component is also reachable without the route**. Decision 809 records that
     * a Livewire component test runs no middleware, and decision 398 records a
     * guard that survived deletion because an outer one refused first; a
     * resolution path the component itself calls is falsifiable in both.
     */
    public function find(int $customerId): Customer
    {
        Tenancy::idOrFail();

        return Customer::query()->findOrFail($customerId);
    }

    /**
     * Whether the tenant has any contacts at all.
     *
     * Separates "nothing has happened yet" from "your search matched nothing".
     * `MessageLog::isEmpty()`'s reason, and it matters more here because the
     * first state has an action attached to it — §1.1's empty state is an
     * invitation into the import screen, and showing that to somebody whose
     * search simply missed would be telling them to re-import contacts they
     * already have.
     *
     * ⚠️ **AN ARCHIVED OR DELETED CONTACT STILL COUNTS, AND EXCLUDING THEM WAS A
     * TRAP THIS SLICE WROTE AND A TEST CAUGHT** (1548). This question gates the
     * whole list UI: answer yes and the screen renders the invitation into
     * Import *instead of* the search box and the room chips. So a tenant whose
     * only contact was deleted got "No customers yet" and no Recently deleted
     * door — a hidden contact with no way back, which is precisely the trap
     * 1502's rule exists to prevent. The archived room had the same exposure
     * and the same answer.
     *
     * What it means is "nobody has ever been on this list", which is the
     * question §1.1's empty state is asking.
     */
    public function isEmpty(): bool
    {
        Tenancy::idOrFail();

        return ! Customer::query()->whereNull('merged_into_id')->exists();
    }

    /**
     * Name, email and phone, case-insensitively (§1.1's *"instant,
     * server-side"*).
     *
     * ⚠️ **THE WILDCARDS IN THE TERM ARE ESCAPED, AND THE REASON IS NOT
     * INJECTION.** The value is bound, so a quote is harmless. What is not
     * harmless is `%`: unescaped, a search for it matches every contact the
     * tenant has, and a search for `_` matches every one-character difference —
     * so the owner types a character that means nothing to them and gets a list
     * that looks like the filter silently failed. `\` is escaped first or it
     * would escape the escapes.
     *
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    private function search(Builder $query, string $search): Builder
    {
        $term = trim($search);

        if ($term === '') {
            return $query;
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
        $pattern = '%'.$escaped.'%';

        return $query->where(function (Builder $builder) use ($pattern): void {
            $builder->where('name', 'ILIKE', $pattern)
                ->orWhere('email', 'ILIKE', $pattern)
                ->orWhere('phone', 'ILIKE', $pattern);
        });
    }
}
