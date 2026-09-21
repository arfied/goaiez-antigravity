<?php

declare(strict_types=1);

namespace App\Modules\X185\Ui;

use App\Modules\X185\Actions\CampaignCreateAction;
use App\Modules\X185\Models\Sequence;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your message sequences'])]
class DigestLine extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $sequenceName = '';

    public string $firstStepChannel = '';

    public string $firstStepDelayHours = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function createSequence(CampaignCreateAction $action): void
    {
        $this->error = null;
        $this->success = null;

        if (trim($this->sequenceName) === '') {
            $this->error = 'Name the sequence.';

            return;
        }

        if (trim($this->firstStepChannel) === '') {
            $this->error = 'Enter the channel for the first step, such as email.';

            return;
        }

        if (trim($this->firstStepDelayHours) === '' || ! is_numeric(trim($this->firstStepDelayHours))) {
            $this->error = 'Enter the delay in hours as a number.';

            return;
        }

        $sequence = $action->createSequence(Tenancy::idOrFail(), trim($this->sequenceName), [[
            'channel' => trim($this->firstStepChannel),
            'delay_hours' => (int) $this->firstStepDelayHours,
        ]]);

        $runningStatus = $sequence->is_active ? 'running' : 'stopped';
        $this->success = 'Sequence "'.$sequence->name.'" is set up and '.$runningStatus.', with a first step on '.trim($this->firstStepChannel).' after '.(int) $this->firstStepDelayHours.' hours. The list below shows the sequence; the step is stored but no screen shows steps yet.';

        $this->sequenceName = '';
        $this->firstStepChannel = '';
        $this->firstStepDelayHours = '';
    }

    public function render()
    {
        $sequences = ($this->businessId > 0)
            ? Sequence::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-185::digest-line', [
            'sequences' => $sequences,
        ]);
    }
}
