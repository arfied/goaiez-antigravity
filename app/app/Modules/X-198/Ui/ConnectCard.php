<?php

declare(strict_types=1);

namespace App\Modules\X198\Ui;

use App\Modules\X198\Actions\MerchantApplyAction;
use App\Modules\X198\Models\MerchantConnection;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Gateway connections'])]
class ConnectCard extends Component
{
    public ?string $error = null;

    public ?string $errorHeading = null;

    public ?string $success = null;

    public function applyForMerchant(MerchantApplyAction $action, int $connectionId): void
    {
        $this->errorHeading = null;
        $this->error = null;
        $this->success = null;
        try {
            $result = $action->handle(Tenancy::idOrFail(), $connectionId);

            if ($result['status'] === 'refused') {
                $this->errorHeading = 'Could not send the application';
                $this->error = $result['message'] ?? 'Refused';
            } elseif ($result['status'] === 'applied') {
                $this->success = "Application sent ({$result['application_ref']}).";
            }
        } catch (ModelNotFoundException) {
            $this->errorHeading = 'Could not send the application';
            $this->error = 'Connection not found.';
        } catch (\Throwable $e) {
            $this->errorHeading = 'Could not send the application';
            $this->error = 'We could not send the application: '.$e->getMessage();
        }
    }

    public function connect(): void
    {
        $this->errorHeading = null;
        $this->error = null;
        $this->success = null;

        if ((string) config('services.stripe.client_id', '') === '') {
            $this->errorHeading = 'Could not connect';
            $this->error = 'Nothing was connected. Connecting a gateway waits on Stripe Connect, and no client id is configured in this checkout yet.';

            return;
        }

        $this->errorHeading = 'Could not connect';
        $this->error = 'Nothing was connected. The Stripe Connect redirect is not built in this checkout yet.';
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);

        $connections = MerchantConnection::where('business_id', Tenancy::idOrFail())->orderBy('id')->get();

        return view('x-198::connect-card', [
            'connections' => $connections,
        ]);
    }
}
