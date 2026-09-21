<?php

declare(strict_types=1);

namespace App\Modules\X205\Ui;

use App\Modules\X205\Actions\AffiliateCreateAction;
use App\Modules\X205\Models\Affiliate;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Affiliates'])]
class AffiliatePortal extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $affiliateCode = '';

    public string $partnerName = '';

    public int $commissionRateBps = 0;

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function createAffiliate(AffiliateCreateAction $action): void
    {
        $this->reset(['success', 'error']);

        if (trim($this->affiliateCode) === '' || trim($this->partnerName) === '') {
            $this->error = 'Affiliate code and partner name are required.';

            return;
        }

        $action->create(Tenancy::idOrFail(), $this->affiliateCode, $this->partnerName, (int) $this->commissionRateBps);

        $this->success = "Created affiliate {$this->partnerName} with code {$this->affiliateCode} at {$this->commissionRateBps} bps. Balance starts at zero. This feeds the affiliate portal; nothing downstream is wired to it yet.";
        $this->reset(['affiliateCode', 'partnerName', 'commissionRateBps']);
    }

    public function render()
    {
        $affiliates = ($this->businessId > 0)
            ? Affiliate::where('business_id', $this->businessId)->orderBy('partner_name')->get()
            : collect();

        return view('x-205::portal', [
            'affiliates' => $affiliates,
        ]);
    }
}
