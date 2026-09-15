<?php

declare(strict_types=1);

namespace App\Modules\X121\Ui;

use App\Modules\X121\Models\EntityHistoryRecord;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Change history'])]
class EntityHistoryViewer extends Component
{
    public string $entityType = '';

    #[Locked]
    public int $entityId = 0;

    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        if ($this->entityId > 0 && $this->businessId > 0) {
            $history = EntityHistoryRecord::where('business_id', $this->businessId)
                ->where('entity_type', $this->entityType)
                ->where('entity_id', $this->entityId)
                ->orderBy('version', 'desc')
                ->get();
        } elseif ($this->businessId > 0 && $this->entityId === 0) {
            $history = EntityHistoryRecord::where('business_id', $this->businessId)
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        } else {
            $history = collect();
        }

        return view('x-121::entity-history-viewer', [
            'history' => $history,
        ]);
    }
}
