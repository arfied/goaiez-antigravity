<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Services\Tenant\LocationContext;
use App\Services\Visibility\LocalVisibility;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Visibility extends Component
{
    public function render(LocalVisibility $visibility, LocationContext $locations): View
    {
        return view('livewire.advanced.visibility', [
            'rows' => $locations->options()->map(fn ($location) => [
                'location' => $location,
                'report' => $visibility->for($location),
            ]),
        ]);
    }
}
