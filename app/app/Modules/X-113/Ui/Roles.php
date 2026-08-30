<?php

declare(strict_types=1);

namespace App\Modules\X113\Ui;

use App\Modules\X113\Models\Role;
use Livewire\Component;

class Roles extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $roles = ($this->businessId > 0)
            ? Role::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-113::roles', [
            'roles' => $roles,
        ]);
    }
}
