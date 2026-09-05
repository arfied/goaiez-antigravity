<?php

declare(strict_types=1);

namespace App\Modules\X122\Ui;

use App\Modules\X122\Actions\ActionReverseAction;
use App\Modules\X122\Models\ActionInvocation;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class ActionLog extends Component
{
    use WithPagination;

    #[Locked]
    public int $businessId = 0;

    public string $search = '';

    public function mount(): void
    {
        $this->businessId = Tenancy::idOrFail();
    }

    public function reverse(int $id, ActionReverseAction $reverser): void
    {
        $invocation = ActionInvocation::where('business_id', $this->businessId)
            ->where('id', $id)
            ->firstOrFail();

        $reverser->handle($invocation->id, $this->businessId, 'operator');
    }

    public function render()
    {
        $query = ActionInvocation::query()
            ->where('business_id', $this->businessId);

        if ($this->search !== '') {
            $query->where('action_name', 'like', '%'.$this->search.'%');
        }

        $invocations = $query
            ->orderByRaw("CASE WHEN status = 'refused' THEN 0 ELSE 1 END ASC")
            ->orderBy('id', 'desc')
            ->paginate(15);

        return view('x-122::action-log', [
            'invocations' => $invocations,
        ]);
    }
}
