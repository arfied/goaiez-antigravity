<?php

declare(strict_types=1);

namespace App\Modules\X218\Ui;

use App\Modules\X218\Actions\InfluencerDealAction;
use App\Modules\X218\Models\InfluencerDeal;
use App\Modules\X218\Models\InfluencerProfile;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Deal tracker'])]
class DealTracker extends Component
{
    public ?int $influencerId = null;

    public ?int $dealAmountCents = null;

    public ?string $success = null;

    public ?string $error = null;

    public function createDeal(InfluencerDealAction $action)
    {
        $this->success = null;
        $this->error = null;

        if (empty($this->influencerId) || empty($this->dealAmountCents)) {
            $this->error = 'Please select an influencer and enter an amount.';

            return;
        }

        try {
            $deal = $action->createDeal(Tenancy::idOrFail(), (int) $this->influencerId, (int) $this->dealAmountCents);
            $this->success = 'Created active deal for '.$deal->deal_amount_cents.' cents. This feeds the tracker; nothing downstream is wired to it yet.';
            $this->reset(['influencerId', 'dealAmountCents']);
        } catch (ModelNotFoundException $e) {
            $this->error = 'Influencer not found.';
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $deals = InfluencerDeal::where('business_id', $businessId)->with('deliverables')->get();
        $influencers = InfluencerProfile::where('business_id', $businessId)->get();

        return view('x-218::deal-tracker', [
            'deals' => $deals,
            'influencers' => $influencers,
        ]);
    }
}
