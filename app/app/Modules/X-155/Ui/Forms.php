<?php

declare(strict_types=1);

namespace App\Modules\X155\Ui;

use App\Modules\X155\Actions\FormCreateAction;
use App\Modules\X155\Models\FormDefinition;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Forms'])]
class Forms extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $newFormName = 'Contact us';

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function createForm()
    {
        if ($this->businessId > 0 && trim($this->newFormName) !== '') {
            $action = app(FormCreateAction::class);
            $action->handle($this->businessId, trim($this->newFormName));
            $this->newFormName = 'Contact us';
        }
    }

    public function render()
    {
        $forms = ($this->businessId > 0)
            ? FormDefinition::withCount([
                'submissions',
                'submissions as spam_count' => fn ($q) => $q->where('is_spam', true),
            ])
                ->where('business_id', $this->businessId)
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('x-155::forms', [
            'forms' => $forms,
        ]);
    }
}
