<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Services\Tenant\LocationContext;
use App\Services\Visibility\VisibilityMovement;
use App\Services\Visibility\VisibilityReadings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class RankTracker extends Component
{
    public function render(VisibilityReadings $readings, LocationContext $locations): View
    {
        $end = CarbonImmutable::today('America/Los_Angeles')->subDay();
        $start = $end->subDays(27);
        $earlierEnd = $start->subDay();
        $earlierStart = $earlierEnd->subDays(27);

        $rows = $locations->options()->map(function ($location) use ($readings, $start, $end, $earlierStart, $earlierEnd) {
            $current = $readings->read($location, $start, $end);
            $earlier = $readings->read($location, $earlierStart, $earlierEnd);
            $movement = VisibilityMovement::between($current, $earlier);

            return [
                'location' => $location,
                'current' => $current,
                'earlier' => $earlier,
                'movement' => $movement,
            ];
        });

        return view('livewire.advanced.rank-tracker', [
            'rows' => $rows,
        ]);
    }
}
