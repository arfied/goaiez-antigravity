<?php

declare(strict_types=1);

namespace App\Modules\X119\Ui;

use App\Modules\X119\Actions\FactTeachAction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Teach a fact'])]
class TeachingBox extends Component
{
    public string $key = '';

    public string $value = '';

    public function teach(FactTeachAction $action): void
    {
        $this->validate(['key' => ['required', 'string', 'max:120'], 'value' => ['required', 'string', 'max:1000']]);
        $action->handle((int) Tenancy::idOrFail(), trim($this->key), trim($this->value), 'volunteered');
        $this->reset('key', 'value');
        session()->flash('status', 'Recorded. It appears on Fact freshness now.');
    }

    public function render()
    {
        return view('x-119::teaching-box');
    }
}
