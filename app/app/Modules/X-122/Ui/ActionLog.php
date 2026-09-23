<?php

declare(strict_types=1);

namespace App\Modules\X122\Ui;

use App\Modules\X122\Actions\ActionReverseAction;
use App\Modules\X122\Actions\GetActionInvocationsAction;
use App\Modules\X122\Models\ActionInvocation;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Layout;
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
        $this->businessId = Tenancy::idOrFail();
    }

    public function reverse(int $id, ActionReverseAction $reverser): void
    {
        $invocation = ActionInvocation::where('business_id', $this->businessId)
            ->where('id', $id)
            ->firstOrFail();

        $reverser->handle($invocation->id, $this->businessId, 'operator');
    }

    public function render(GetActionInvocationsAction $action)
    {
        try {
            $invocations = $action->handle($this->businessId, $this->search);
        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to load action log';
            $invocations = collect();
        }

        return view('x-122::action-log', [
            'invocations' => $invocations,
        ]);
    }
}
