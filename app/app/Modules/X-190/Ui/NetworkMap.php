<?php

declare(strict_types=1);

namespace App\Modules\X190\Ui;

use App\Models\Business;
use App\Modules\X190\Models\PartnerPool;
use App\Modules\X190\Models\ReferralListing;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Partner network'])]
class NetworkMap extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $companyName = '';

    public string $category = '';

    public string $territoryZip = '';

    public string $success = '';

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
        abort_if($this->businessId === 0, 404);
        if ($this->companyName === '') {
            $this->companyName = Business::query()->whereKey($this->businessId)->value('name') ?? '';
        }
    }

    public function listBusiness(): void
    {
        $this->validate([
            'companyName' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:80'],
            'territoryZip' => ['required', 'regex:/^\d{5}$/'],
        ]);
        ReferralListing::updateOrCreate(
            ['business_id' => $this->businessId],
            ['company_name' => trim($this->companyName), 'category' => trim($this->category), 'territory_zip' => $this->territoryZip, 'is_listed' => true],
        );
        $this->success = 'Listed '.trim($this->companyName).' for '.trim($this->category).' referrals in '.$this->territoryZip.'. Other businesses on the platform can now find you from their Referral slots.';
    }

    public function unlist(): void
    {
        ReferralListing::where('business_id', $this->businessId)->update(['is_listed' => false]);
        $this->success = 'Your listing is hidden. Nobody can find you from their Referral slots until you list again.';
    }

    public function render()
    {
        $partners = ($this->businessId > 0)
            ? PartnerPool::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-190::network-map', [
            'partners' => $partners,
            'listing' => ReferralListing::where('business_id', $this->businessId)->first(),
        ]);
    }
}
