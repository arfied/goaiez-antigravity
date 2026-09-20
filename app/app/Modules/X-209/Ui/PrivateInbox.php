<?php

declare(strict_types=1);

namespace App\Modules\X209\Ui;

use App\Modules\X209\Actions\FixerCommandAction;
use App\Modules\X209\Models\FixerCommand;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Fixer inbox'])]
class PrivateInbox extends Component
{
    #[Locked]
    public int $businessId = 0;

    public int $staffPersonId = 0;

    public string $smsBody = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function process(): void
    {
        $this->success = null;
        $this->error = null;

        if ($this->staffPersonId === 0) {
            $this->error = 'Staff Person ID is required.';

            return;
        }
        if (trim($this->smsBody) === '') {
            $this->error = 'SMS Body cannot be empty.';

            return;
        }

        $bizId = Tenancy::idOrFail();
        $result = FixerCommandAction::processStaffSms($bizId, $this->staffPersonId, $this->smsBody);
        $this->success = 'Processed SMS command from staff. Parsed delay: '.($result['eta_delayed'] ?? 0).' minutes. This feeds the inbox; nothing downstream is wired to it yet.';
    }

    public function render()
    {
        $commands = ($this->businessId > 0)
            ? FixerCommand::where('business_id', $this->businessId)->orderByDesc('id')->get()
            : collect();

        return view('x-209::private-inbox', [
            'commands' => $commands,
        ]);
    }
}
