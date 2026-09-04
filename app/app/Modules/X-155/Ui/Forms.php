<?php

declare(strict_types=1);

namespace App\Modules\X155\Ui;

use App\Modules\X155\Models\FormDefinition;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Forms extends Component
{
    #[Locked]
    public int $businessId = 0;

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
