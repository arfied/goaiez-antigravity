<?php

declare(strict_types=1);

namespace App\Modules\X10\Ui;

use App\Modules\X10\Actions\TerritoryDefineAction;
use App\Modules\X10\Models\Territory;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your service areas'])]
class TerritoryMap extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $name = '';

    public ?string $error = null;

    public ?string $success = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function defineArea(TerritoryDefineAction $action): void
    {
        $this->error = null;
        $this->success = null;

        $name = trim($this->name);
        if ($name === '') {
            $this->error = 'Name cannot be empty.';

            return;
        }

        try {
            $territory = $action->handle(Tenancy::idOrFail(), $name);
            $this->success = 'Service area '.$territory->name.' defined successfully.';
            $this->name = '';
        } catch (\Throwable $e) {
            $this->error = 'Error defining service area: '.$e->getMessage();
        }
    }

    public function render()
    {
        $territories = ($this->businessId > 0)
            ? Territory::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-10::territory-map', [
            'territories' => $territories,
        ]);
    }
}
