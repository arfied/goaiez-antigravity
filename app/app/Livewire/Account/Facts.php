<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Enums\IndustryFamily;
use App\Services\Facts\BusinessFactKey;
use App\Services\Facts\BusinessFacts;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

#[Layout('components.account.layout', ['heading' => 'Your business facts'])]
final class Facts extends Component
{
    /** @var array<string, string> */
    public array $facts = [];

    public function mount(BusinessFacts $store): void
    {
        abort_if(Tenancy::id() === null, 403);
        $stored = $store->all(Tenancy::idOrFail());
        foreach (array_keys(BusinessFactKey::all()) as $key) {
            $this->facts[$key] = $stored[$key] ?? '';
        }
    }

    public function save(BusinessFacts $store): void
    {
        $rules = [];
        foreach (BusinessFactKey::all() as $key => $def) {
            $rules["facts.{$key}"] = ['nullable', 'string', 'max:'.$def['max']];
        }
        $rules['facts.'.BusinessFactKey::YEARS_IN_BUSINESS] = ['nullable', 'integer', 'min:0', 'max:200'];
        $rules['facts.'.BusinessFactKey::INDUSTRY] = ['nullable', 'in:'.implode(',', IndustryFamily::values())];
        $this->validate($rules);

        $biz = Tenancy::idOrFail();
        foreach (array_keys(BusinessFactKey::all()) as $key) {
            $store->set($biz, $key, (string) ($this->facts[$key] ?? ''));
        }

        Toaster::success('Saved — your site reads these the next time it drafts.');
    }

    public function render()
    {
        return view('livewire.account.facts', ['defs' => BusinessFactKey::all()]);
    }
}
