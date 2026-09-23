<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Models\Customer;
use App\Services\Campaigns\DormancySegment;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Segments extends Component
{
    public function render(DormancySegment $segment): View
    {
        return view('livewire.advanced.segments', [
            'customersCount' => Customer::query()->count(),
            'dormantCount' => $segment->apply(Customer::query())->count(),
            'dormancyDays' => $segment->cutoff()->diffInDays(now()),
        ]);
    }
}
