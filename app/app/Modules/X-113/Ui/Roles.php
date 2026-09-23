<?php

declare(strict_types=1);

namespace App\Modules\X113\Ui;

use App\Modules\X113\Actions\RoleCreateAction;
use App\Modules\X113\Models\Role;
use App\Support\Tenancy;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Roles'])]
class Roles extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $name = '';

    public string $description = '';

    public ?string $error = null;

    public ?string $success = null;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function createRole(RoleCreateAction $action): void
    {
        $this->error = null;
        $this->success = null;

        if (trim($this->name) === '') {
            $this->error = 'Name cannot be empty.';

            return;
        }

        try {
            $role = $action->handle(Tenancy::idOrFail(), $this->name, $this->description === '' ? null : $this->description);
            $this->success = 'Created role '.$role->name.'. This feeds the roles list; nothing downstream is wired to it yet.';
            $this->name = '';
            $this->description = '';
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $roles = ($this->businessId > 0)
            ? Role::where('business_id', $this->businessId)->orderBy('name')->get()
            : collect();

        return view('x-113::roles', [
            'roles' => $roles,
        ]);
    }
}
