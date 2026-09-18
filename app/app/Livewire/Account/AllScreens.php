<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Support\Account\OwnerNav;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The All screens page listing the 185 module screens by family.
 */
#[Layout('components.account.layout', ['heading' => 'All screens'])]
final class AllScreens extends Component
{
    public function mount(): void
    {
        abort_if(Tenancy::id() === null, 403);
    }

    public function render(): View
    {
        $catalog = OwnerNav::catalog();

        $families = [];
        foreach ($catalog as $item) {
            $parts = explode('.', $item->route);
            $family = $parts[0];
            $families[$family][] = $item;
        }

        ksort($families);

        foreach ($families as $family => &$items) {
            usort($items, fn ($a, $b) => strcmp($a->label, $b->label));
        }

        return view('livewire.account.all-screens', [
            'families' => $families,
        ]);
    }
}
