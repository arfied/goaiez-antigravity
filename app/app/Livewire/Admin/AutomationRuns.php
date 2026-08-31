<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\AutomationRunStatus;
use App\Livewire\Admin\Concerns\AdminTable;
use App\Models\AuditLogEntry;
use App\Models\AutomationRun;
use App\Models\Business;
use App\Services\AuditService;
use App\Support\Admin\AdminAccess;
use App\Support\Admin\Column;
use App\Support\MailFailure;
use App\Support\Tenancy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LogicException;

/**
 * Automation runs, for one account — the first screen composed from the shell.
 *
 * ⛔ **IT WAS A TENANT-SCOPED SCREEN WITH NO WAY TO NAME A TENANT, AND THAT IS
 * WHAT 500'D EVERY STAFF SIGN-IN** (decision 5730). `AutomationRun` is
 * tenant-owned, so `AdminTable::rows()` opens with `Tenancy::idOrFail()` — and
 * this screen is reached by platform staff, who own no business and therefore
 * have no tenant. `LoginResponse` sends staff to the first item
 * `AdminNav::for()` grants them, which is this one, so the *happy path of every
 * staff login* ended in `TenantNotResolved`.
 *
 * ⚠️ **NOTHING IN THAT CHAIN WAS INDIVIDUALLY WRONG AND THAT IS THE WHOLE
 * LESSON.** The guard is right — softening it paginates every tenant's rows on
 * one screen, which is the breach it exists to prevent. The nav is right — it
 * lists what staff may see. The sign-in is right — there is no `/admin` index
 * to send them to and inventing one is a screen nobody asked for. **What was
 * missing was the account.** A screen that reads one tenant's rows has to be
 * able to say whose, and this one could only inherit whatever tenant happened
 * to be in context — which for the people it is built for is none, and for a
 * member of staff who also owns a business would have been *their own*.
 *
 * ⚠️ **IT NO LONGER PROVES THE SHELL IS THREE DECLARATIONS AND A VIEW, AND THE
 * SHELL IS NOT WHAT WAS WRONG.** `BUILD-PLAN` §4.1's claim survives: the
 * pagination, sorting, searching, filtering and selection are still the trait's
 * and this screen writes none of them. What it adds is the one thing the trait
 * cannot supply for it — the tenant its rows belong to.
 *
 * ## Named, not listed, and read inside that account's tenancy
 *
 * {@see AccountAudit}'s pattern, for its reasons rather than by imitation.
 * `businesses` carries two RLS policies and neither admits platform staff, so
 * the runtime role cannot enumerate accounts at all (decision 569); an operator
 * names one, `Tenancy::actingAs()` sets `app.business_id` to it, and nothing
 * else becomes visible. A wrong number and a number belonging to nobody are
 * deliberately indistinguishable.
 *
 * ⚠️ **THE READ IS RECORDED IN THE ACCOUNT IT OPENED.** `28` §14.1 attributes
 * internal *reads* of sensitive objects, not only writes, and every other staff
 * surface that names an account files `business.viewed_by_staff` into that
 * tenant's own log — `PhiTenants`, `TermsAcceptances`, `TenantLocations`,
 * `SendingControls`, `Support\Accounts`. A run row names the automation, the
 * location and when the system acted for a customer; a staff read of it that
 * left no trace would let somebody walk the business id space with nothing
 * anywhere to say so.
 *
 * ⚠️ **AND IT CAN THROW, WHICH IS CORRECT RATHER THAN OVERSIGHT** —
 * `PhiTenants::lookUp()`'s reasoning. The audit write is not wrapped and
 * `$businessId` is set after it, so an unwritable `audit_log` refuses the whole
 * lookup instead of showing the account unrecorded.
 */
final class AutomationRuns extends Component
{
    /** @use AdminTable<AutomationRun> */
    use AdminTable;

    /** What the operator typed. An account number; see {@see self::resolve()}. */
    public string $reference = '';

    /**
     * The account they have named, or null.
     *
     * ⚠️ **`#[Locked]` BECAUSE {@see self::resolve()} IS THE ONLY THING THAT MAY
     * SET IT, AND THE AUDIT ROW IS WRITTEN THERE.** Without the attribute this
     * is an ordinary public property that arrives in the update payload, so
     * anybody who can reach this component could point it at any account and let
     * `render()` read that tenant's runs with `resolve()` never called and
     * nothing recorded anywhere — the exact harm the `business.viewed_by_staff`
     * entry exists to close, walked around rather than through.
     */
    #[Locked]
    public ?int $businessId = null;

    public string $businessName = '';

    /**
     * One query per render, however many times the view asks.
     *
     * Not `#[Computed]`: that caches across a request too, and a plain private
     * field makes the lifetime visible — one request. The component is rebuilt
     * from its snapshot on every Livewire round trip, so nothing survives that
     * should not.
     *
     * @var ?LengthAwarePaginator<int, AutomationRun>
     */
    private ?LengthAwarePaginator $page = null;

    /**
     * The tenant-scoped builder, alive only inside {@see self::runs()}.
     *
     * @var ?Builder<AutomationRun>
     */
    private ?Builder $scoped = null;

    public function mount(): void
    {
        // Repeated on the component rather than left to the route's `can:`
        // middleware — decision 630: a route-gate test passes while `mount()` is
        // wide open, because `can:` refuses during route matching and the
        // component never runs.
        $this->authorize(AdminAccess::GATE);
    }

    /**
     * Name an account, and file the fact that we did.
     *
     * A miss says so plainly rather than 404ing the screen: the operator has
     * typed a number, and "no account with that number" is the answer to that,
     * where a 404 reads as the screen itself being broken.
     *
     * ⚠️ One entry per resolved lookup, never per render. Livewire re-renders on
     * every property update, so recording the read path would file a row per
     * keystroke in the search box and make the log unreadable — which is its own
     * kind of unaudited. A miss writes nothing: there is no tenant to file it
     * under, and refusing a number discloses nothing about an account that does
     * not exist.
     */
    public function resolve(AuditService $audit): void
    {
        $this->authorize(AdminAccess::GATE);

        $this->reset(['businessId', 'businessName']);
        $this->resetErrorBag();

        // The shell's own reset, not pagination of this screen's making: naming
        // a second account while on page four of the first would otherwise open
        // it on a page it may not have.
        $this->resetPage();

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
                ['surface' => 'admin.automation-runs'],
            ),
        );

        $this->businessId = $id;
        $this->businessName = (string) $business->name;
    }

    public function clearAccount(): void
    {
        $this->reset(['businessId', 'businessName', 'reference']);
        $this->clearFilters();
    }

    /**
     * The page of runs, read inside the named account's tenancy.
     *
     * ⚠️ **NULL IS A RENDERED STATE, NOT A FAILURE.** No account named means no
     * query at all — not an unscoped one, and not `Tenancy::idOrFail()` thrown
     * at somebody who has just signed in. The view shows the invitation instead.
     *
     * @return ?LengthAwarePaginator<int, AutomationRun>
     */
    public function runs(): ?LengthAwarePaginator
    {
        if ($this->businessId === null) {
            return null;
        }

        if ($this->page instanceof LengthAwarePaginator) {
            return $this->page;
        }

        /** @var LengthAwarePaginator<int, AutomationRun> $page */
        $page = Tenancy::actingAs($this->businessId, function (): LengthAwarePaginator {
            // The trait's own rows(), tenancy and all. Everything it returns is
            // materialised before the closure ends — `paginate()` runs its count
            // and its select immediately, and no column rendered here is a
            // relation, so nothing lazy-loads later outside this boundary.
            $this->scoped = AutomationRun::query();

            try {
                return $this->rows();
            } finally {
                // Cleared whatever happens. A builder left on the component
                // outlives the tenancy it was built in, and the next call to
                // query() would hand the shell a query bound to an account
                // nobody named on this request.
                $this->scoped = null;
            }
        });

        return $this->page = $page;
    }

    /**
     * @return array<int, Column>
     */
    protected function columns(): array
    {
        return [
            Column::make('automation_key', 'Automation')->sortable()->searchable(),
            Column::make('status')
                ->sortable()
                ->format(fn (AutomationRunStatus $status): string => match ($status) {
                    AutomationRunStatus::Succeeded => 'Done',
                    AutomationRunStatus::HandedOff => 'Done, by hand',
                    AutomationRunStatus::Skipped => 'Not needed',
                    AutomationRunStatus::Failed => 'Needs a look',
                    // ⚠️ **NOT "Needs a look", THOUGH IT DOES.** A `Failed` row is
                    // usually an attempt on the way to a success — `handle()`'s
                    // catch rethrows so the queue retries — and this one is a
                    // process that was killed and never came back. They read
                    // identically to an operator otherwise, and the difference is
                    // the whole reason the case exists (9961).
                    AutomationRunStatus::Abandoned => 'Stopped mid-run',
                    AutomationRunStatus::Running => 'In progress',
                }),
            Column::make('location_id', 'Location')->numeric(),
            Column::make('started_at', 'Started')->sortable(),
            Column::make('finished_at', 'Finished')->sortable(),
            Column::make('output', 'Why')->format(self::whyText(...)),
        ];
    }

    /**
     * ⛔ **THE SMALLEST HONEST THING, NOT A TRANSLATION TABLE FOR ALL 142
     * AUTOMATIONS' REASON VOCABULARIES** (wave 37 lane E, decision 10506).
     * `output.reason` is the one key every `AutopilotJob` subclass writes on
     * every `Skipped` row (`AutopilotJob::recordSkip()`) and on `Abandoned`
     * (`AutopilotJob::RUN_ABANDONED`) — its own docblock calls it *"read by
     * whoever is reconstructing a week's silence from the database"*, so it
     * was already written to be read by a person, just never on this screen.
     * A dozen other automations write their own richer shapes under other
     * keys (`refusal`, a nested outcome object) — those are deliberately NOT
     * translated here, because guessing a prose rendering for a shape this
     * method has not read is worse than showing nothing for it. `error` is
     * deliberately never rendered here either, and **9378's reason for that is
     * narrower than it reads — corrected 2026-08-28 (11344, 11453).** It says
     * *"a provider's own text, never vetted for this audience"*; ⛔ **on the
     * mail path that text was an account holder's own email address**, because
     * an SMTP refusal names the identity that failed and nothing on the path
     * caught it. ✅ **The column no longer takes it** — `AutopilotJob::handle()`
     * writes {@see MailFailure::runError()}, the class for
     * everything but our own `MailNotDeliverable` — so the reason to keep it
     * unrendered is now the ordinary one and not a leak. ⚠️ **The full message
     * still reaches `failed_jobs.exception` on the rethrow**, a table with no
     * tenant predicate and no crypto-shred.
     *
     * ⚠️ **`Str::headline()`, NOT A PER-CODE SENTENCE TABLE.** `recordSkip()`'s
     * reasons are already spaced English ("kill switch", "tenant suspended");
     * the handful written as a bare enum value ("budget_exhausted",
     * "nothing_found") are snake_case identifiers, never PII and never a
     * stack trace, and a title-case pass reads honestly as "the system's own
     * short answer" without inventing owner-facing copy per code this method
     * was not written to maintain.
     */
    private static function whyText(mixed $output): string
    {
        $reason = is_array($output) ? ($output['reason'] ?? null) : null;

        if (! is_string($reason) || $reason === '') {
            return '—';
        }

        return str($reason)->limit(80)->headline()->toString();
    }

    /**
     * The builder built inside the named account's tenancy, never one of the
     * shell's own making.
     *
     * ⚠️ **REACHED ANY OTHER WAY THERE IS NO ACCOUNT NAMED, AND FAILING LOUDLY
     * IS THE POINT.** `AdminTable` calls this from `rows()` and from
     * `runBulkAction()`, and only the first of those is wrapped by
     * {@see self::runs()}. Returning `AutomationRun::query()` unconditionally
     * would build a query against whatever tenant happened to be in context —
     * none for the staff this screen is for, and *their own account* for a
     * member of staff who also owns a business, which is the quiet wrong answer
     * rather than the loud one.
     *
     * @return Builder<AutomationRun>
     */
    protected function query(): Builder
    {
        return $this->scoped ?? throw new LogicException(
            'AutomationRuns::query() was reached outside the account tenancy that '
            .'runs() establishes. Nothing here may read a tenant\'s automation runs '
            .'without naming whose they are.'
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function filters(): array
    {
        return [
            'status' => collect(AutomationRunStatus::cases())
                ->mapWithKeys(fn (AutomationRunStatus $s): array => [$s->value => ucfirst(str_replace('_', ' ', $s->value))])
                ->all(),
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.automation-runs', [
            'runs' => $this->runs(),
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
