<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Models\Call;
use App\Models\Campaign;
use App\Models\Review;
use App\Models\ReviewAsk;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Reports extends Component
{
    public function render(): View
    {
        return view('livewire.advanced.reports', [
            'reviewsThisMonth' => Review::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            'asksThisMonth' => ReviewAsk::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            'callsThisMonth' => Call::query()->where('started_at', '>=', now()->startOfMonth())->count(),
            'campaignsThisMonth' => Campaign::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            'businessId' => Tenancy::id(),
        ]);
    }
}
