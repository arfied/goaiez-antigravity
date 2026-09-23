<?php

declare(strict_types=1);

namespace App\Modules\X217\Ui;

use App\Modules\X217\Actions\AffiliateRecruitAction;
use App\Modules\X217\Models\AffiliateProspect;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Recruitment Pipeline'])]
class RecruitPipeline extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $partnerName = '';

    public string $email = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function recruitProspect(AffiliateRecruitAction $action): void
    {
        $this->error = null;
        $this->success = null;

        if (empty($this->partnerName) || empty($this->email)) {
            $this->error = 'Partner name and email are required.';

            return;
        }

        $prospect = $action->recruitProspect(Tenancy::idOrFail(), $this->partnerName, $this->email);

        $this->success = 'Recorded prospect '.$prospect->partner_name.' and pitched. This feeds the recruitment pipeline lists; nothing downstream is wired to it yet.';
        $this->partnerName = '';
        $this->email = '';
    }

    public function render()
    {
        $prospects = ($this->businessId > 0)
            ? AffiliateProspect::where('business_id', $this->businessId)->with('offers')->get()
            : collect();

        return view('x-217::recruit-pipeline', [
            'prospects' => $prospects,
        ]);
    }
}
