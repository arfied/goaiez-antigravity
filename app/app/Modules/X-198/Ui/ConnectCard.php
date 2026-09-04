<?php

declare(strict_types=1);

namespace App\Modules\X198\Ui;

use App\Modules\X198\Actions\MerchantApplyAction;
use App\Modules\X198\Models\MerchantConnection;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Component;

class ConnectCard extends Component
{
    public ?string $error = null;

    public ?string $success = null;

    public function applyForMerchant(MerchantApplyAction $action, int $connectionId): void
    {
        $this->error = null;
        $this->success = null;
        try {
            $result = $action->handle(Tenancy::idOrFail(), $connectionId);

            if ($result['status'] === 'refused') {
                $this->error = $result['message'] ?? 'Refused';
            } elseif ($result['status'] === 'applied') {
                $this->success = "Application sent ({$result['application_ref']}).";
            }
        } catch (ModelNotFoundException) {
            $this->error = 'Connection not found.';
        } catch (\Throwable $e) {
            $this->error = 'We could not send the application: '.$e->getMessage();
        }
    }

    public function connect(): void
    {
        $this->error = null;
        $this->success = null;

        if ((string) config('services.stripe.client_id', '') === '') {
            $this->error = 'Connecting a gateway waits on Stripe Connect: no client id is configured yet.';

            return;
        }

        $this->error = 'Stripe Connect redirect lands in week 2.';
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);

        $connections = MerchantConnection::where('business_id', Tenancy::idOrFail())->get();

        return view('x-198::connect-card', [
            'connections' => $connections,
        ]);
    }
}
