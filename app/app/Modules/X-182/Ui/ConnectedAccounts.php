<?php

declare(strict_types=1);

namespace App\Modules\X182\Ui;

use App\Modules\X182\Models\SocialAccount;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ConnectedAccounts extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $accounts = ($this->businessId > 0)
            ? SocialAccount::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-182::connected-accounts', [
            'accounts' => $accounts,
        ]);
    }
}
