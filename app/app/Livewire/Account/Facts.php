<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Enums\IndustryFamily;
use App\Services\Facts\BusinessFactKey;
use App\Services\Facts\BusinessFacts;
use App\Services\Industry\IndustryResolver;
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
        $biz = Tenancy::idOrFail();
        $defs = BusinessFactKey::forBusiness($biz);
        $stored = $store->all($biz);
        foreach (array_keys($defs) as $key) {
            $this->facts[$key] = $stored[$key] ?? '';
        }
    }

    public function save(BusinessFacts $store): void
    {
        $biz = Tenancy::idOrFail();
        $defs = BusinessFactKey::forBusiness($biz);

        $rules = [];
        foreach ($defs as $key => $def) {
            $rules["facts.{$key}"] = ['nullable', 'string', 'max:'.$def['max']];
        }
        $rules['facts.'.BusinessFactKey::YEARS_IN_BUSINESS] = ['nullable', 'integer', 'min:0', 'max:200'];
        $rules['facts.'.BusinessFactKey::INDUSTRY] = ['nullable', 'in:'.implode(',', IndustryFamily::values())];
        $this->validate($rules);

        $store->set($biz, BusinessFactKey::INDUSTRY, (string) ($this->facts[BusinessFactKey::INDUSTRY] ?? ''));

        $defs = BusinessFactKey::forBusiness($biz);

        foreach (array_keys($defs) as $key) {
            if ($key !== BusinessFactKey::INDUSTRY) {
                $store->set($biz, $key, (string) ($this->facts[$key] ?? ''));
            }
        }

        Toaster::success('Saved — your site reads these the next time it drafts.');
    }

    public function render(IndustryResolver $resolver)
    {
        $biz = Tenancy::idOrFail();
        $defs = BusinessFactKey::all();
        $allDefs = BusinessFactKey::forBusiness($biz);
        $industryDefs = array_diff_key($allDefs, $defs);
        $industryLabel = $resolver->for($biz)['family']?->label();

        return view('livewire.account.facts', [
            'defs' => $defs,
            'industryDefs' => $industryDefs,
            'industryLabel' => $industryLabel,
        ]);
    }
}
