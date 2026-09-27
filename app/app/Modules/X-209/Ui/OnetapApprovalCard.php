<?php

declare(strict_types=1);

namespace App\Modules\X209\Ui;

use App\Modules\X209\Actions\FixerDelegateAction;
use App\Modules\X209\Actions\FixerExecuteAction;
use App\Modules\X209\Models\FixerCommand;
use App\Modules\X209\Models\FixerLadder;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'One-tap approval'])]
class OnetapApprovalCard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public int $autoLevel = 3;

    public ?string $toast = null;

    public function mount(DefaultsRegistry $defaults): void
    {
        $this->businessId = Tenancy::id() ?? 0;
        $this->autoLevel = $defaults->int('fixer.ladder.auto_level');
    }

    public function approve(int $id, FixerExecuteAction $action): void
    {
        $command = $action->execute($this->businessId, $id);
        $ladder = FixerLadder::where('business_id', $this->businessId)->where('action_name', $command->parsed_intent)->first();
        $level = $ladder ? $ladder->current_level : 0;
        $this->toast = 'Approved — '.$command->parsed_intent.' is now level '.$level.'. Nothing is sent to the customer yet.';
    }

    public function delegate(int $id, FixerDelegateAction $action): void
    {
        $action->delegate($this->businessId, $id, 'declined at one-tap');
        $this->toast = 'Handed to owner.';
    }

    public function render()
    {
        $commands = ($this->businessId > 0)
            ? FixerCommand::where('business_id', $this->businessId)
                ->where('status', 'pending_approval')
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('x-209::onetap-approval-card', [
            'commands' => $commands,
        ]);
    }
}
