<?php

declare(strict_types=1);

namespace App\Modules\X155\Ui;

use App\Modules\X155\Models\FormSubmission;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Form submissions'])]
class SubmissionsThread extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $submissions = ($this->businessId > 0)
            ? FormSubmission::where('business_id', $this->businessId)
                ->with('formDefinition')
                ->latest()
                ->get()
            : collect();

        return view('x-155::submissions-thread', [
            'submissions' => $submissions,
        ]);
    }
}
