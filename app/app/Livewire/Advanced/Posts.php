<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Models\GrowthPage;
use App\Models\Location;
use App\Services\Content\GrowthPages;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Posts extends Component
{
    public function render(GrowthPages $pages): View
    {
        $businessId = Auth::user()->business_id;
        $location = Location::where('business_id', $businessId)->first();

        $rows = GrowthPage::query()
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        // dd($rows);

        return view('livewire.advanced.posts', [
            'rows' => $rows,
            'websiteUrl' => $location?->website_url,
        ]);
    }
}
