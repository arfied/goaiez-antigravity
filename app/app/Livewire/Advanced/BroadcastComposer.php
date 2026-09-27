<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Enums\CampaignAudience;
use App\Enums\CampaignKind;
use App\Exceptions\CampaignRefused;
use App\Models\Customer;
use App\Services\Campaigns\Campaigns;
use App\Services\Campaigns\DormancySegment;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class BroadcastComposer extends Component
{
    public string $title = '';

    public string $body = '';

    public function render(DormancySegment $segment): View
    {
        return view('livewire.advanced.broadcast-composer', [
            'businessId' => Tenancy::id(),
            'dormantCount' => $segment->apply(Customer::query())->count(),
        ]);
    }

    public function saveDraft(Campaigns $campaigns): void
    {
        $this->validate(['title' => ['required', 'string', 'max:120'], 'body' => ['required', 'string', 'max:320']]);
        try {
            $campaigns->draft(
                name: $this->title,
                bodyTemplate: $this->body,
                audience: CampaignAudience::Dormant,
                actor: 'user:'.(int) auth()->id(),
                kind: CampaignKind::Broadcast,
            );
        } catch (CampaignRefused $e) {
            $this->addError('body', $e->getMessage());

            return;
        }
        session()->flash('status', 'Draft saved. Nothing has been sent.');
        $this->redirect(route('advanced.broadcasts'));
    }
}
