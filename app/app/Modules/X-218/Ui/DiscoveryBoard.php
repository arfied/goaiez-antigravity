<?php

declare(strict_types=1);

namespace App\Modules\X218\Ui;

use App\Modules\X218\Actions\InfluencerDiscoverAction;
use App\Modules\X218\Models\InfluencerProfile;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Discovery board'])]
class DiscoveryBoard extends Component
{
    public string $handle = '';

    public string $platform = 'instagram';

    public int $audienceSize = 15000;

    public float $engagementRate = 4.20;

    public ?string $success = null;

    public ?string $error = null;

    public function discover(): void
    {
        $this->success = null;
        $this->error = null;

        if (trim($this->handle) === '') {
            $this->error = 'Handle cannot be empty.';

            return;
        }

        $businessId = Tenancy::idOrFail();

        $profile = InfluencerDiscoverAction::discoverInfluencer($businessId, $this->handle, $this->platform, $this->audienceSize, $this->engagementRate);

        $this->success = 'Discovered '.$profile->handle.' on '.$profile->platform.'. This feeds the influencer lists; nothing downstream is wired to it yet.';
        $this->handle = '';
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $influencers = InfluencerProfile::where('business_id', $businessId)->get();

        return view('x-218::discovery-board', [
            'influencers' => $influencers,
        ]);
    }
}
