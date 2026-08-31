<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Models\Citation;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Home extends Component
{
    public function render(): View
    {
        $businessId = Tenancy::id();

        $citationsCount = Citation::where('business_id', $businessId)->count();
        $consistentCount = Citation::where('business_id', $businessId)->where('nap_status', 'consistent')->count();
        $mismatchCount = Citation::where('business_id', $businessId)->where('nap_status', 'mismatch')->count();

        return view('livewire.advanced.home', [
            'citationsCount' => $citationsCount,
            'consistentCount' => $consistentCount,
            'mismatchCount' => $mismatchCount,
        ]);
    }
}
