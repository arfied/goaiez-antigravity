<?php

declare(strict_types=1);

namespace App\Modules\X155\Ui;

use App\Modules\X155\Models\FormSubmission;
use Livewire\Component;

class SubmissionsThread extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $submissions = ($this->businessId > 0)
            ? FormSubmission::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-155::submissions-thread', [
            'submissions' => $submissions,
        ]);
    }
}
