<?php

declare(strict_types=1);

namespace App\Modules\X173\Ui;

use App\Modules\X173\Actions\ConflictResolveAction;
use App\Modules\X173\Models\AccountingSyncConflict;
use App\Support\Tenancy;
use Livewire\Component;

class ConflictsListView extends Component
{
    public array $resolutions = [];
    public array $messages = [];

    public function resolve(int $conflictId, ConflictResolveAction $action)
    {
        $resolution = $this->resolutions[$conflictId] ?? '';
        $result = $action->handle(Tenancy::idOrFail(), $conflictId, $resolution);

        $this->messages[$conflictId] = $result['message'] ?? $result['status'];
    }

    public function render()
    {
        $conflicts = AccountingSyncConflict::where('business_id', Tenancy::idOrFail())->get();

        return view('x-173::conflicts-list', [
            'conflicts' => $conflicts,
        ]);
    }
}
