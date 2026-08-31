<?php

declare(strict_types=1);

namespace App\Support\Admin;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * One column in an admin table.
 *
 * A value object rather than a Blade partial per column: `28` Parts 9-11 assume
 * a table component arrives free with Filament, and it does not (decision 87).
 * Building it per screen is how row 6 doubles in cost and then pays again at
 * rows 17 and 22 — `BUILD-PLAN` §4.1 is explicit about this, which is why the
 * shell lands in FOUND-07 rather than with the first screen that needs it.
 */
final class Column
{
    private ?Closure $formatter = null;

    private bool $sortable = false;

    private bool $searchable = false;

    /** Renders as data rather than prose — tabular numerals, mono face. */
    private bool $numeric = false;

    private function __construct(
        public readonly string $key,
        public readonly string $label,
    ) {}

    public static function make(string $key, ?string $label = null): self
    {
        return new self($key, $label ?? str($key)->headline()->toString());
    }

    public function sortable(bool $sortable = true): self
    {
        $this->sortable = $sortable;

        return $this;
    }

    /**
     * Include this column in the search.
     *
     * Only ever applied to columns declared here, never to arbitrary input —
     * see AdminTable::applySearch(), where that restriction is what stops a
     * search box becoming a way to probe columns the screen does not show.
     */
    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function numeric(bool $numeric = true): self
    {
        $this->numeric = $numeric;

        return $this;
    }

    /**
     * @param  Closure(mixed, Model): (string|Htmlable)  $formatter
     */
    public function format(Closure $formatter): self
    {
        $this->formatter = $formatter;

        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isNumeric(): bool
    {
        return $this->numeric;
    }

    public function render(Model $record): mixed
    {
        $value = data_get($record, $this->key);

        return $this->formatter === null ? $value : ($this->formatter)($value, $record);
    }
}
