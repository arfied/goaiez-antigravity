<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

use App\Support\Admin\DetailSection;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Read-only record layout — once.
 *
 * A screen declares sections() and record(). It renders no field list of its
 * own, which is the point: a detail layout built per screen is a detail layout
 * that eventually iterates every attribute, and the first model that reaches is
 * usually the one holding credentials.
 *
 * The record is resolved through the tenant-scoped query, so an id from another
 * tenant does not resolve at all rather than resolving and being hidden.
 *
 * @template TModel of Model
 */
trait AdminDetail
{
    /**
     * @return array<int, DetailSection>
     */
    abstract protected function sections(): array;

    /**
     * @return TModel
     */
    abstract protected function record(): Model;

    /**
     * @return Collection<int, array{title: string, description: ?string, entries: array<int, array{label: string, value: mixed, numeric: bool}>}>
     */
    public function detailSections(): Collection
    {
        Tenancy::idOrFail();

        $record = $this->record();

        return collect($this->sections())->map(fn (DetailSection $section): array => [
            'title' => $section->title,
            'description' => $section->description,
            'entries' => $section->render($record),
        ]);
    }
}
