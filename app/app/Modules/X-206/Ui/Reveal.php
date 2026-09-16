<?php

declare(strict_types=1);

namespace App\Modules\X206\Ui;

use App\Modules\X206\Actions\CredentialRevealAction;
use App\Modules\X206\Models\Credential;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Reveal credential'])]
class Reveal extends Component
{
    #[Locked]
    public int $businessId = 0;

    public ?int $revealedId = null;

    public ?string $revealedSecret = null;

    public ?string $error = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId ?: Tenancy::id() ?? 0;
        abort_if($this->businessId === 0, 404);
    }

    public function reveal(int $credentialId, CredentialRevealAction $action): void
    {
        $this->error = null;
        $this->revealedSecret = null;
        $this->revealedId = null;

        $result = $action->handle($this->businessId, $credentialId, auth()->id(), request()->ip());

        if ($result['status'] === 'permitted') {
            $this->revealedId = $credentialId;
            $this->revealedSecret = $result['secret'];
        } else {
            $this->error = 'That credential is not yours to reveal. The attempt was logged.';
        }
    }

    public function render()
    {
        return view('x-206::reveal', [
            'credentials' => Credential::where('business_id', $this->businessId)
                ->orderBy('service_name')
                ->get(),
        ]);
    }
}
