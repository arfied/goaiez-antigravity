<?php

declare(strict_types=1);

namespace App\Modules\X182\Ui;

use App\Modules\X182\Models\SocialAccount;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Connected accounts'])]
class ConnectedAccounts extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId ?: Tenancy::id() ?? 0;
        abort_if($this->businessId === 0, 404);
    }

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
