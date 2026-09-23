<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Services\Tenant\LocationContext;
use App\Services\Visibility\CompetitorSignals;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Competitors extends Component
{
    public function render(CompetitorSignals $signals, LocationContext $locations): View
    {
        $rows = collect();

        foreach ($locations->options() as $location) {
            $rows->push((object) [
                'location' => $location,
                'comparison' => $signals->compare($location),
            ]);
        }

        return view('livewire.advanced.competitors', [
            'rows' => $rows,
        ]);
    }
}
