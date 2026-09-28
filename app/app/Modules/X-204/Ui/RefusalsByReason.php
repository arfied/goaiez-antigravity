<?php

declare(strict_types=1);

namespace App\Modules\X204\Ui;

use App\Modules\X204\Domain\ConsentService;
use App\Modules\X204\Models\SendPermit;
use App\Services\Crm\NeverContact;
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

        $customer = app(NeverContact::class)->applyToNumber($this->suppressPhone, auth()->user());

        if ($customer) {
            $this->success = "Added {$this->suppressPhone} to this screen's refusal list, stopped any follow-up sequence, and marked {$customer->name} as Never contact, so no text will be sent to them.";
        } else {
            $this->success = "Added {$this->suppressPhone} to this screen's refusal list and stopped any follow-up sequence to them. No customer has this number, so other texts are not blocked: add them as a customer and choose Never contact them.";
        }

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
            $this->success = "Check for {$this->decidePhone}: allowed by this screen's list. Every real text is still checked against the platform's own consent record.";
        } else {
            $this->success = "Check for {$this->decidePhone}: refused by this screen's list (Reason: {$result['reason']}). Every real text is still checked against the platform's own consent record.";
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
