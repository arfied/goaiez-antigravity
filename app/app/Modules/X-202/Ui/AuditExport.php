<?php

declare(strict_types=1);

namespace App\Modules\X202\Ui;

use App\Modules\X202\Models\ApprovalItem;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Approval history'])]
class AuditExport extends Component
{
    public function render()
    {
        abort_unless(Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();
        $decided = ApprovalItem::where('business_id', $businessId)
            ->whereNotNull('decided_at')
            ->orderByDesc('decided_at')
            ->get();

        return view('x-202::audit-export', ['decided' => $decided]);
    }
}
