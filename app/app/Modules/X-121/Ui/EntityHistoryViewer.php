<?php

declare(strict_types=1);

namespace App\Modules\X121\Ui;

use App\Modules\X121\Models\EntityHistoryRecord;
use Livewire\Component;

class EntityHistoryViewer extends Component
{
    public string $entityType = '';

    public int $entityId = 0;

    public int $businessId = 0;

    public function render()
    {
        $history = ($this->entityId > 0 && $this->businessId > 0)
            ? EntityHistoryRecord::where('business_id', $this->businessId)
                ->where('entity_type', $this->entityType)
                ->where('entity_id', $this->entityId)
                ->orderBy('version', 'desc')
                ->get()
            : collect();

        return view('x-121::entity-history-viewer', [
            'history' => $history,
        ]);
    }
}
