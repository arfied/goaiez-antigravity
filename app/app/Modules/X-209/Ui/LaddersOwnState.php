<?php

declare(strict_types=1);

namespace App\Modules\X209\Ui;

use App\Modules\X209\Actions\FixerApproveAction;
use App\Modules\X209\Models\FixerLadder;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Autopilot ladder'])]
class LaddersOwnState extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $actionName = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function approveAction(FixerApproveAction $action): void
    {
        $this->success = null;
        $this->error = null;

        if (trim($this->actionName) === '') {
            $this->error = 'Action name is required.';

            return;
        }

        $ladder = $action->approve(Tenancy::idOrFail(), $this->actionName);

        $this->success = 'Promoted action to level '.$ladder->current_level.'. This feeds the autonomy list; nothing downstream is wired to it yet.';
        $this->actionName = '';
    }

    public function render()
    {
        $ladders = ($this->businessId > 0)
            ? FixerLadder::where('business_id', $this->businessId)->orderBy('action_name')->get()
            : collect();

        return view('x-209::ladders-own-state', [
            'ladders' => $ladders,
        ]);
    }
}
