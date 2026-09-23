<?php

declare(strict_types=1);

namespace App\Modules\X204\Ui;

use App\Modules\X204\Domain\ConsentService;
use App\Modules\X204\Models\SendPermit;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Consent Refusals by Reason'])]
class RefusalsByReason extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $suppressPhone = '';

    public string $suppressChannel = 'sms';

    public string $suppressReason = 'opt_out';

    public string $decidePhone = '';

    public string $decideChannel = 'sms';

    public string $success = '';

    public string $error = '';

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId ?: Tenancy::id() ?? 0;
        abort_if($this->businessId === 0, 404);
    }

    public function suppress(ConsentService $service): void
    {
        $this->reset(['success', 'error']);

        if (trim($this->suppressPhone) === '') {
            $this->error = 'Phone number is required to suppress.';

            return;
        }

        $service->suppress(Tenancy::idOrFail(), $this->suppressPhone, $this->suppressChannel, $this->suppressReason);

        $this->success = "Number {$this->suppressPhone} has been suppressed. This feeds the margin lists; nothing downstream is wired to it yet.";
        $this->reset(['suppressPhone']);
    }

    public function decide(ConsentService $service): void
    {
        $this->reset(['success', 'error']);

        if (trim($this->decidePhone) === '') {
            $this->error = 'Phone number is required to decide.';

            return;
        }

        $result = $service->decide(Tenancy::idOrFail(), $this->decidePhone, $this->decideChannel);

        if ($result['granted'] === true) {
            $this->success = "Check for {$this->decidePhone}: Granted. This feeds the margin lists; nothing downstream is wired to it yet.";
        } else {
            $this->success = "Check for {$this->decidePhone}: Refused (Reason: {$result['reason']}). This feeds the margin lists; nothing downstream is wired to it yet.";
        }
        $this->reset(['decidePhone']);
    }

    public function render()
    {
        $refusals = ($this->businessId > 0)
            ? SendPermit::where('business_id', $this->businessId)->where('permit_status', 'refused')->latest()->get()
            : collect();

        return view('x-204::refusals-by-reason', [
            'refusals' => $refusals,
        ]);
    }
}
