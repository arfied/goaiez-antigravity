<?php

declare(strict_types=1);

namespace App\Modules\X190\Ui;

use App\Modules\X190\Actions\SlotDeclineAction;
use App\Modules\X190\Actions\SlotProposeAction;
use App\Modules\X190\Models\PartnerPool;
use App\Modules\X190\Models\ReferralListing;
use App\Modules\X190\Models\ReferralSlot;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Referral slots'])]
class SlotBoard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $category = '';

    public string $territoryZip = '';

    public array $partnerName = [];   // keyed by slot id

    public array $found = [];   // keyed by slot id → array of listings

    public string $success = '';

    public function openSlot(): void
    {
        $this->validate([
            'category' => ['required', 'string', 'max:80'],
            'territoryZip' => ['required', 'regex:/^\d{5}$/'],
        ]);
        ReferralSlot::create([
            'business_id' => $this->businessId,
            'category' => trim($this->category),
            'territory_zip' => $this->territoryZip,
            'is_network_enabled' => true,
            'status' => 'open',
        ]);
        $this->success = 'Opened a '.trim($this->category).' slot in '.$this->territoryZip.'. Propose a partner for it below.';
        $this->category = '';
        $this->territoryZip = '';
    }

    public function findPartners(int $slotId): void
    {
        $slot = ReferralSlot::where('business_id', $this->businessId)->findOrFail($slotId);
        $this->found[$slotId] = ReferralListing::query()
            ->where('is_listed', true)
            ->where('category', $slot->category)
            ->where('territory_zip', $slot->territory_zip)
            ->where('business_id', '!=', $this->businessId)
            ->orderBy('company_name')
            ->limit(20)
            ->get(['company_name', 'category', 'territory_zip'])
            ->map(fn ($l) => ['company_name' => (string) $l->company_name])
            ->all();
    }

    public function proposeFound(int $slotId, string $companyName, SlotProposeAction $action): void
    {
        $this->partnerName[$slotId] = $companyName;
        $this->proposePartner($slotId, $action);
    }

    public function proposePartner(int $slotId, SlotProposeAction $action): void
    {
        $name = trim((string) ($this->partnerName[$slotId] ?? ''));
        if ($name === '') {
            $this->addError('partnerName.'.$slotId, 'Name the company you want to shortlist.');

            return;
        }
        $slot = ReferralSlot::where('business_id', $this->businessId)->findOrFail($slotId);
        $action->proposePartner($this->businessId, (int) $slot->id, $name, (string) $slot->category, (string) $slot->territory_zip);
        $this->success = 'Proposed '.$name.' for the '.$slot->category.' slot in '.$slot->territory_zip.'. It is on the Partner network screen now.';
        unset($this->partnerName[$slotId]);
    }

    public function decline(int $slotId, SlotDeclineAction $action): void
    {
        $slot = ReferralSlot::where('business_id', $this->businessId)->findOrFail($slotId);
        $partner = PartnerPool::where('business_id', $this->businessId)
            ->where('category', $slot->category)
            ->where('territory_zip', $slot->territory_zip)
            ->where('is_declined', false)
            ->first();

        if ($partner === null) {
            $this->addError('decline.'.$slotId, 'There is nothing to decline on this slot.');

            return;
        }

        $action->decline($this->businessId, (int) $slot->id, (int) $partner->id);
        $this->success = 'Declined. The slot is open again and that company will not be suggested for it.';
    }

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
        abort_if($this->businessId === 0, 404);
    }

    public function render()
    {
        $referralSlots = ($this->businessId > 0)
            ? ReferralSlot::where('business_id', $this->businessId)->get()
            : collect();

        $pool = ($this->businessId > 0)
            ? PartnerPool::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-190::slot-board', [
            'referralSlots' => $referralSlots,
            'pool' => $pool,
        ]);
    }
}
