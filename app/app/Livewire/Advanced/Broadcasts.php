<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Exceptions\CampaignRefused;
use App\Models\Campaign;
use App\Models\CampaignReply;
use App\Services\Campaigns\Campaigns;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Broadcasts extends Component
{
    public function confirm(int $campaignId, Campaigns $campaigns): void
    {
        $campaign = Campaign::query()->findOrFail($campaignId);
        try {
            $campaigns->confirm($campaign, 'user:'.(int) auth()->id());
            $enrolled = $campaigns->enrol($campaign);
        } catch (CampaignRefused $e) {
            $this->addError('confirm', $e->getMessage());

            return;
        }
        session()->flash('status', 'Confirmed. '.$enrolled.' recipients enrolled; sending starts on the next run, within fifteen minutes.');
    }

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
