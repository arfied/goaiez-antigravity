<?php

declare(strict_types=1);

namespace App\Modules\X122\Ui;

use App\Modules\X122\Actions\ActionReverseAction;
use App\Modules\X122\Actions\GetActionInvocationsAction;
use App\Modules\X122\Models\ActionInvocation;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.account.layout', ['heading' => 'Action log'])]
class ActionLog extends Component
{
    use WithPagination;

    #[Locked]
    public int $businessId = 0;

    public string $search = '';

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function reverse(int $id, ActionReverseAction $reverser): void
    {
        if ($this->businessId === 0) {
            $this->errorMessage = 'No business is in view — open one from Tenant locations first.';

            return;
        }

        $invocation = ActionInvocation::where('business_id', $this->businessId)
            ->where('id', $id)
            ->firstOrFail();

        $reverser->handle($invocation->id, $this->businessId, 'operator');
    }

    public function render(GetActionInvocationsAction $action)
    {
        try {
            $invocations = $this->businessId === 0 ? collect() : $action->handle($this->businessId, $this->search);
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to load action log';
            $invocations = collect();
        }

        return view('x-122::action-log', [
            'invocations' => $invocations,
        ]);
    }
}
