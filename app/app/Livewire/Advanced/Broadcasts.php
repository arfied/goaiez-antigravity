<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Models\Campaign;
use App\Models\CampaignReply;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Broadcasts extends Component
{
    public function render(): View
    {
        $campaigns = Campaign::withCount('recipients')
            ->addSelect([
                'replies_count' => CampaignReply::selectRaw('count(*)')
                    ->whereColumn('campaign_id', 'campaigns.id'),
            ])
            ->latest()
            ->limit(50)
            ->get();

        $campaignsThisMonth = $campaigns->where('created_at', '>=', now()->startOfMonth())->count();
        $recipientsEnrolled = $campaigns->sum('recipients_count');

        return view('livewire.advanced.broadcasts', [
            'businessId' => Tenancy::id(),
            'campaigns' => $campaigns,
            'campaignsThisMonth' => $campaignsThisMonth,
            'recipientsEnrolled' => $recipientsEnrolled,
        ]);
    }
}
