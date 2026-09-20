<?php

declare(strict_types=1);

namespace App\Modules\X206\Ui;

use App\Modules\X206\Models\Credential;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Connections'])]
class Connections extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $serviceName = '';
    public string $secret = '';
    public ?string $success = null;
    public ?string $error = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId ?: Tenancy::id() ?? 0;
        abort_if($this->businessId === 0, 404);
    }

    public function submit(\App\Modules\X206\Actions\CredentialStoreAction $action): void
    {
        $this->reset(['success', 'error']);

        if (empty($this->serviceName) || empty($this->secret)) {
            $this->error = 'Service name and secret are required.';
            return;
        }

        $cred = $action->handle($this->businessId, $this->serviceName, $this->secret);

        $this->success = "Recorded credentials for {$cred->service_name} (hint: {$cred->key_hint}). Nothing downstream is wired to it yet.";
        $this->reset(['serviceName', 'secret']);
    }

    public function render()
    {
        $creds = ($this->businessId > 0)
            ? Credential::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-206::connections', [
            'credentials' => $creds,
        ]);
    }
}
