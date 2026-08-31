<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

use App\Support\Admin\BulkAction;
use App\Support\Admin\Column;
use App\Support\Tenancy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Sorting, pagination, filtering, search and bulk actions — once.
 *
 * `28` Parts 9-11 describe the Ops Console as growing "the existing Filament v4
 * panel". There is no Filament panel and Filament is not the stack (decision
 * 87), so tables, filters, bulk actions and detail layouts are ours to build.
 * `BUILD-PLAN` §4.1 calls the mitigation not optional: build the shell once
 * here, compose every Ops Console screen from it, or pay for it again at rows 6,
 * 17 and 22.
 *
 * A screen composes this trait and declares columns(), query(), and optionally
 * filters() and bulkActions(). It writes no pagination, no sort links, and no
 * selection handling.
 *
 * **Sorting and searching are restricted to declared columns.** Both take input
 * straight from the URL, so binding them to arbitrary column names would let a
 * visitor order by — and thereby infer — a column the screen never shows.
 *
 * @template TModel of Model
 */
trait AdminTable
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortColumn = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDirection = 'asc';

    /** @var array<string, string> */
    #[Url(as: 'f', except: [])]
    public array $activeFilters = [];

    /** @var array<int, int|string> */
    public array $selected = [];

    public int $perPage = 25;

    /**
     * @return array<int, Column>
     */
    abstract protected function columns(): array;

    /**
     * @return Builder<TModel>
     */
    abstract protected function query(): Builder;

    /**
     * @return array<string, array<string, string>> Filter key => [value => label]
     */
    protected function filters(): array
    {
        return [];
    }

    /**
     * @return array<int, BulkAction<TModel>>
     */
    protected function bulkActions(): array
    {
        return [];
    }

    public function sortBy(string $column): void
    {
        if (! $this->isSortable($column)) {
            // Silent rather than an error: this is reachable by editing the URL,
            // and a visitor probing column names should learn nothing from the
            // difference between "not sortable" and "does not exist".
            return;
        }

        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedActiveFilters(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->activeFilters = [];
        $this->search = '';
        $this->resetPage();
    }

    /**
     * Run a bulk action over the current selection.
     */
    public function runBulkAction(string $key, ?string $reason = null): void
    {
        $action = collect($this->bulkActions())->firstWhere('key', $key);

        if (! $action instanceof BulkAction) {
            throw new RuntimeException("Unknown bulk action [{$key}].");
        }

        if ($action->isDestructive() && blank($reason)) {
            // `29` §19.5 — destructive actions carry a typed reason. Enforced
            // here rather than only in the UI, because the UI is not the
            // boundary.
            throw new RuntimeException("Action [{$key}] requires a reason.");
        }

        if ($this->selected === []) {
            return;
        }

        // Re-resolved through the scoped query rather than trusting the ids on
        // the component. `selected` arrives from the browser, so a caller can
        // put anything in it — resolving through query() means another tenant's
        // id simply does not come back.
        $records = $this->query()->whereKey($this->selected)->get();

        $action->run($records, $reason);

        $this->selected = [];
    }

    /**
     * @return LengthAwarePaginator<int, TModel>
     */
    public function rows(): LengthAwarePaginator
    {
        // Fails closed rather than paginating every tenant's rows if a screen is
        // ever reached without a tenant established.
        Tenancy::idOrFail();

        $query = $this->query();

        $this->applySearch($query);
        $this->applyFilters($query);
        $this->applySort($query);

        return $query->paginate($this->perPage);
    }

    /**
     * @return Collection<int, Column>
     */
    public function visibleColumns(): Collection
    {
        return collect($this->columns());
    }

    /**
     * The values a filter offers, for rendering its control.
     *
     * The view renders only what this returns, and applyFilters() accepts only
     * what this returns — one declaration driving both, so a filter cannot be
     * displayed without being enforceable or enforced without being displayed.
     *
     * @return array<string, string>
     */
    public function filterOptions(string $key): array
    {
        return $this->filters()[$key] ?? [];
    }

    /**
     * @param  Builder<TModel>  $query
     */
    private function applySearch(Builder $query): void
    {
        if (blank($this->search)) {
            return;
        }

        $searchable = collect($this->columns())->filter->isSearchable();

        if ($searchable->isEmpty()) {
            return;
        }

        $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->search).'%';

        $query->where(function (Builder $q) use ($searchable, $term): void {
            foreach ($searchable as $column) {
                $q->orWhere($column->key, 'ilike', $term);
            }
        });
    }

    /**
     * @param  Builder<TModel>  $query
     */
    private function applyFilters(Builder $query): void
    {
        $declared = $this->filters();

        foreach ($this->activeFilters as $key => $value) {
            // Only filters the screen declared, and only values it offered.
            if (! isset($declared[$key]) || blank($value)) {
                continue;
            }

            if (! array_key_exists($value, $declared[$key])) {
                continue;
            }

            $query->where($key, $value);
        }
    }

    /**
     * @param  Builder<TModel>  $query
     */
    private function applySort(Builder $query): void
    {
        if (! $this->isSortable($this->sortColumn)) {
            return;
        }

        $direction = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $query->orderBy($this->sortColumn, $direction);
    }

    private function isSortable(string $column): bool
    {
        return collect($this->columns())
            ->filter->isSortable()
            ->contains(fn (Column $c): bool => $c->key === $column);
    }
}
