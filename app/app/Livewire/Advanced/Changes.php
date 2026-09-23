<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Services\Actuation\ActuationTiers;
use App\Services\Actuation\SiteChanges as SiteChangeLog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Changes extends Component
{
    public function render(SiteChangeLog $log, ActuationTiers $tiers): View
    {
        return view('livewire.advanced.changes', [
            'cards' => collect($log->history($tiers))->take(50),
        ]);
    }
}
