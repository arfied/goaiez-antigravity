<?php

declare(strict_types=1);

namespace App\Modules\X198\Ui;

use App\Modules\X198\Actions\MerchantApplyAction;
use App\Support\Tenancy;
use Livewire\Component;

class ConnectCard extends Component
{
    public ?string $error = null;
    public ?string $status = null;

    public function applyForMerchant(MerchantApplyAction $action): void
    {
        $this->error = null;
        $result = $action->execute(Tenancy::idOrFail());

        if ($result['status'] === 'refused') {
            $this->error = $result['reason'] === 'no_adapter_bound' 
                ? 'No processor adapter bound.' 
                : 'Merchant application already in progress.';
        } else {
            $this->status = 'applied';
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);
        return view('x-198::connect-card');
    }
}
