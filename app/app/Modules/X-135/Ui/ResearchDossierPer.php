<?php

declare(strict_types=1);

namespace App\Modules\X135\Ui;

use App\Modules\X135\Models\ResearchRun;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ResearchDossierPer extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    public int $prospectId = 0;

    public function render()
    {
        $dossier = ($this->businessId > 0 && $this->prospectId > 0)
            ? ResearchRun::where('business_id', $this->businessId)->where('prospect_id', $this->prospectId)->with(['icebreakers', 'signals'])->latest('id')->first()
            : null;

        return view('x-135::research-dossier-per', [
            'dossier' => $dossier,
        ]);
    }
}
