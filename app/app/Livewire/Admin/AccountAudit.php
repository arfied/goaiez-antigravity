<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\AdminTable;
use App\Models\AuditLogEntry;
use App\Services\AuditExplorer;
use App\Support\Admin\AdminAccess;
use App\Support\Admin\Column;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LogicException;

/**
 * "Everyone who touched account Y" — `28` §14.1, half of the audit explorer.
 *
 * ⚠️ **`super_admin` alone, and that is narrower than it first looks useful.**
 * `28` §9.2 puts the audit explorer in the **Platform** section and §14.1 says
 * so again in the same sentence, so it takes `AdminAccess::GATE` rather than
 * `SupportAccess::GATE` — a `support_lead` cannot open it, even to read back
 * their own session. Decision 560's lesson runs in this direction too: the
 * screen reads a tenant's entire compliance record, and the conservative
 * reading of a gate the document has already named is the one it named.
 *
 * ## Why the account is typed rather than picked from a list
 *
 * Decision 569, unchanged and for the same reason: `businesses` and `audit_log`
 * are both RLS-`FORCE`d on `app.business_id`, a platform admin has no tenant,
 * and a list would need a third policy admitting staff to every tenant. Naming
 * one account sets `app.business_id` to it and nothing else becomes visible.
 * A wrong number and a number belonging to nobody are indistinguishable.
 *
 * ## Composed from the shell, which is the point of building it here
 *
 * `BUILD-PLAN` §4.1 calls composing Ops screens from `AdminTable` "not
 * optional", and this is the first screen outside FOUND-07 to test whether that
 * holds. It does, with one adaptation: `rows()` opens with
 * `Tenancy::idOrFail()`, so the call is wrapped in the named account's tenancy
 * rather than the trait being loosened. **That fail-closed line is the reason
 * the shell was safe to reuse here at all** — a screen that reads another
 * tenant's rows by naming one has to be unable to run without naming one.
 */
final class AccountAudit extends Component
{
    /** @use AdminTable<AuditLogEntry> */
    use AdminTable;

    /** What the operator typed. A business id; see resolve(). */
    public string $reference = '';

    /**
     * The account they have named, or null.
     *
     * ⚠️ **`#[Locked]` BECAUSE {@see self::resolve()} IS THE ONLY THING THAT MAY
     * SET IT, AND THE `audit.explored` ROW IS WRITTEN THERE** — added at
     * decision 5735, found while building the same guard onto
     * {@see AutomationRuns}. This screen shipped without it while the other five
     * staff surfaces that name an account all carried it (`PhiTenants`,
     * `TermsAcceptances`, `TenantLocations`, `SendingControls`,
     * `Support\Accounts`), and the consequence is the one those five write up at
     * length: an ordinary public property arrives in the update payload, so
     * anybody who can reach this component could point it at any account and let
     * `render()` read that tenant's whole compliance record with `resolve()`
     * never called and nothing recorded anywhere. **That is the harm the
     * self-referential audit row exists to prevent, walked around rather than
     * through** — and on this screen the thing walked around is the audit trail
     * itself.
     */
    #[Locked]
    public ?int $businessId = null;

    /**
     * The account's name, for the reader's own screen.
     *
     * ⚠️ **`#[Locked]` EVEN THOUGH IT GATES NOTHING, BECAUSE ON *THIS* SCREEN
     * THE LABEL IS PART OF THE ATTESTATION** — decision 5792. The id above and
     * this name are one fact, written by one method, in the same breath as the
     * `audit.explored` row `resolve()` files. Leaving half of that fact in the
     * update payload lets the client relabel a recorded read: the trail says
     * account 41 and the screen says whatever was posted, which is the audit
     * explorer disagreeing with the audit log about whose record is open. **A
     * screenshot of this screen is the artefact an auditor is handed**, and it
     * costs one attribute for it to be unable to lie.
     *
     * ⛔ **WHAT LOCKING IT DOES NOT DO, SO NOBODY READS MORE INTO IT.** The
     * `audit.explored` row is keyed on the id, never on this string, so the
     * record was never forgeable — only the picture of it. And the actor here
     * is already a `super_admin` who could mislead a colleague in a dozen ways
     * this attribute cannot reach. It closes the one path where **this
     * application** would have produced the misleading artefact itself.
     *
     * ⚠️ **`AutomationRuns::$businessName` AND `TenantLocations::$name` ARE THE
     * SAME SHAPE AND ARE DELIBERATELY LEFT ALONE** (5793). Neither screen is an
     * attestation about who read what, so on those two the argument above is
     * only tidiness, and tidiness is not a reason to edit a screen this
     * branch was not sent to review.
     */
    #[Locked]
    public string $businessName = '';

    /** The entry whose metadata is open, or null. */
    public ?int $inspecting = null;

    /**
     * One query per render, however many times the view asks.
     *
     * Not `#[Computed]`: that caches across a request too, and this is a plain
     * private field precisely so the reader can see the lifetime is one
     * request. The component is rebuilt from its snapshot on every Livewire
     * round trip, so nothing survives that shouldn't.
     *
     * @var ?LengthAwarePaginator<int, AuditLogEntry>
     */
    private ?LengthAwarePaginator $page = null;

    /**
     * The tenant-scoped builder, alive only inside the explorer's closure.
     *
     * @var ?Builder<AuditLogEntry>
     */
    private ?Builder $scoped = null;

    public function mount(): void
    {
        $this->authorize(AdminAccess::GATE);
    }

    /**
     * Name an account, and file the fact that we did.
     *
     * `28` §14.1 attributes internal **reads** of sensitive objects, not only
     * writes, so `AuditExplorer::openAccount()` writes an `audit.explored` row
     * into the trail being opened. That row is never filtered out of what this
     * screen then renders: an auditor asking who has been through a customer's
     * record has to be shown us.
     */
    public function resolve(AuditExplorer $explorer): void
    {
        $this->authorize(AdminAccess::GATE);

        $this->reset(['businessId', 'businessName', 'inspecting']);
        $this->resetErrorBag();
        $this->resetPage();

        $reference = trim($this->reference);

        if ($reference === '' || ! ctype_digit($reference)) {
            $this->addError('reference', 'Enter the account number from the ticket.');

            return;
        }

        $id = (int) $reference;

        $name = $explorer->openAccount($id, $this->actor());

        if ($name === null) {
            $this->addError('reference', 'No account with that number.');

            return;
        }

        $this->businessId = $id;
        $this->businessName = $name;
    }

    public function clearAccount(): void
    {
        $this->reset(['businessId', 'businessName', 'reference', 'inspecting']);
        $this->clearFilters();
    }

    /**
     * Open one entry's metadata.
     *
     * An id from the browser and no re-check here on purpose: {@see entries()}
     * resolves it through the same tenant-scoped query the table renders, so an
     * id belonging to another account comes back as nothing rather than as
     * something hidden.
     */
    public function inspect(int $entryId): void
    {
        $this->inspecting = $this->inspecting === $entryId ? null : $entryId;
    }

    /**
     * The page of entries, read inside the named account's tenancy.
     *
     * @return ?LengthAwarePaginator<int, AuditLogEntry>
     */
    public function entries(AuditExplorer $explorer): ?LengthAwarePaginator
    {
        if ($this->businessId === null) {
            return null;
        }

        if ($this->page instanceof LengthAwarePaginator) {
            return $this->page;
        }

        /** @var LengthAwarePaginator<int, AuditLogEntry> $page */
        $page = $explorer->entriesFor(
            $this->businessId,
            // The trait's own rows(), tenancy and all. Everything it returns is
            // materialised before the closure ends — `paginate()` runs its count
            // and its select immediately, and no column rendered here is a
            // relation, so nothing lazy-loads later outside this boundary.
            function (Builder $scoped): LengthAwarePaginator {
                $this->scoped = $scoped;

                try {
                    return $this->rows();
                } finally {
                    // Cleared whatever happens. A builder left on the component
                    // outlives the tenancy it was built in, and the next call to
                    // query() would hand the shell a query bound to an account
                    // nobody named on this request.
                    $this->scoped = null;
                }
            },
        );

        return $this->page = $page;
    }

    /**
     * The metadata of the open entry, if it is on this page.
     *
     * @return ?array<string, mixed>
     */
    public function openMetadata(AuditExplorer $explorer): ?array
    {
        if ($this->inspecting === null) {
            return null;
        }

        $page = $this->entries($explorer);

        if ($page === null) {
            return null;
        }

        $entry = collect($page->items())
            ->first(fn (AuditLogEntry $e): bool => $e->getKey() === $this->inspecting);

        if (! $entry instanceof AuditLogEntry) {
            return null;
        }

        /** @var ?array<string, mixed> $metadata */
        $metadata = $entry->metadata;

        return $metadata ?? [];
    }

    /**
     * @return array<int, Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('id', 'Entry')->numeric()->sortable(),
            Column::make('created_at', 'When')->sortable()->format(
                fn (mixed $at): string => $at instanceof CarbonInterface
                    ? $at->toDayDateTimeString()
                    // Nullable on this table, and an audit row with no time is
                    // worth showing as one rather than as an empty cell.
                    : 'no timestamp',
            ),
            Column::make('actor', 'Who')->sortable()->searchable(),
            Column::make('action', 'What')->sortable()->searchable(),
            // ⚠️ `Model`, not `AuditLogEntry`. `Column::format()` is declared
            // against the base class, and narrowing a closure parameter is
            // unsound — PHPStan rejects it, correctly, because the shell may
            // hand any model to any formatter.
            Column::make('entity_type', 'Record')->format(
                fn (mixed $type, Model $entry): string => $type === null
                    ? '—'
                    : class_basename((string) $type).' #'.(string) $entry->getAttribute('entity_id'),
            ),
        ];
    }

    /**
     * The builder the explorer handed us, never one of our own making.
     *
     * ⚠️ **This screen cannot name `AuditLogEntry`, and a lint enforces it** —
     * `only two services touch the compliance record`. The first version of
     * this method called `AuditLogEntry::query()` and the lint written in the
     * same slice caught it, which is the whole argument for writing the lint:
     * a tenant-scoped model queried from a screen with no tenant throws, and
     * the one-line fix that presents itself is `withoutGlobalScopes()`.
     *
     * Taking the builder from {@see entries()} instead means the query cannot
     * exist outside `AuditExplorer::entriesFor()`'s tenancy — not by convention,
     * but because there is nothing here to build one from.
     *
     * @return Builder<AuditLogEntry>
     */
    protected function query(): Builder
    {
        // The shell only ever calls this from inside rows(), which this screen
        // only ever calls from inside the closure above. Reached any other way
        // there is no account named, and failing loudly is the point.
        $query = $this->scoped ?? throw new LogicException(
            'AccountAudit::query() was reached outside the account tenancy that '
            .'AuditExplorer::entriesFor() establishes. Nothing here may read the '
            .'audit log without naming whose it is.'
        );

        // ⚠️ Newest first by `id`, and only while nothing else is sorting.
        //
        // The trait's default is no order at all, which for an append-only log
        // means Postgres' physical order — right by accident today and wrong the
        // first time a row is updated, which is exactly what this table forbids
        // and therefore exactly the bug nobody would look for.
        //
        // `id`, not `created_at`: that column is nullable here (Laravel's own
        // `timestamps()` default) and Postgres sorts NULL *first* on a
        // descending order (decision 289), so an undated row would sit above
        // every dated one at the top of an audit trail. In an append-only log
        // the id *is* the order things happened in.
        //
        // The guard matters because `applySort()` appends, and an unconditional
        // order here would be the primary one — every sort button on the screen
        // would then do nothing, silently.
        if ($this->sortColumn === '') {
            $query->orderByDesc('id');
        }

        return $query;
    }

    public function render(AuditExplorer $explorer): View
    {
        return view('livewire.admin.account-audit', [
            'entries' => $this->entries($explorer),
            'metadata' => $this->openMetadata($explorer),
        ]);
    }

    /**
     * The same actor format the rest of the console writes — `user:{id}`.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'admin' : 'user:'.(string) $id;
    }
}
