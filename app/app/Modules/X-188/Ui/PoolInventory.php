<?php

declare(strict_types=1);

namespace App\Modules\X188\Ui;

use App\Enums\UserRole;
use App\Modules\X188\Actions\NumberAssignAction;
use App\Modules\X188\Models\NumberPool;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Number pool'])]
class PoolInventory extends Component
{
    #[Locked]
    public int $businessId = 0;

    public ?string $error = null;

    public ?string $success = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function claimNumber(NumberAssignAction $action): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->error = null;
        $this->success = null;

        $result = $action->handle($this->businessId);

        if ($result['status'] === 'unassigned') {
            $this->error = 'No numbers are free in the pool right now, so nothing was assigned. Nothing was bought and no carrier was contacted.';

            return;
        }

        $this->success = 'Your number is '.$result['phone_number'].'. It came from the platform pool — nothing was purchased.';
    }

    public function render()
    {
        $numbersByAreaCode = ($this->businessId > 0)
            ? NumberPool::where('business_id', $this->businessId)->get()->groupBy('area_code')
            : collect();

        return view('x-188::pool-inventory', [
            'numbersByAreaCode' => $numbersByAreaCode,
        ]);
    }
}
