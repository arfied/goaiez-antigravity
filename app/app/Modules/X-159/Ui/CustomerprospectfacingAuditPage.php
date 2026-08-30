<?php

declare(strict_types=1);

namespace App\Modules\X159\Ui;

use App\Modules\X159\Models\Audit;
use Livewire\Component;

class CustomerprospectfacingAuditPage extends Component
{
    public int $businessId = 0;

    public int $prospectId = 0;

    public function render()
    {
        $audit = ($this->businessId > 0 && $this->prospectId > 0)
            ? Audit::where('business_id', $this->businessId)->where('prospect_id', $this->prospectId)->with('findings')->latest('id')->first()
            : null;

        return view('x-159::customerprospectfacing-audit-page', [
            'audit' => $audit,
        ]);
    }
}
