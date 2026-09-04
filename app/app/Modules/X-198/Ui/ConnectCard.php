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

    public function applyForMerchant(MerchantApplyAction $action, int $connectionId): void
    {
        $this->error = null;
        try {
            $result = $action->handle(Tenancy::idOrFail(), $connectionId);

            if ($result['status'] === 'refused') {
                $this->error = $result['message'] ?? 'Refused';
            }
        } catch (ModelNotFoundException) {
            $this->error = 'Connection not found.';
        }
    }

    public function connect(): void
    {
        $this->error = 'Waiting on Track 1 merge.';
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
