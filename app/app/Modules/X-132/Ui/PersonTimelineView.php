<?php

declare(strict_types=1);

namespace App\Modules\X132\Ui;

use App\Enums\UserRole;
use App\Modules\X121\Actions\PersonLookupAction;
use App\Modules\X132\Actions\PersonResolveAction;
use App\Modules\X132\Models\ResolutionEvidence;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Person timeline'])]
class PersonTimelineView extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    public int $personId = 0;

    public int $personPick = 0;

    public string $fieldName = '';

    public string $fieldValue = '';

    public string $error = '';

    public string $success = '';

    public function mount()
    {
        abort_unless(auth()->check() && (auth()->user()->hasRole(UserRole::Owner, UserRole::Manager)), 403);
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function recordEvidence(PersonResolveAction $action): void
    {
        $this->error = '';
        $this->success = '';

        if ($this->personPick === 0) {
            $this->error = 'Please select a person.';

            return;
        }

        if (trim($this->fieldValue) === '') {
            $this->error = 'Field value cannot be empty.';

            return;
        }

        $evidenceField = [
            'name' => $this->fieldName,
            'value' => $this->fieldValue,
            'source' => 'manual_entry',
            'confidence' => 0.99,
        ];

        $action->resolveIdentity(Tenancy::idOrFail(), $this->personPick, [$evidenceField]);

        $this->personId = $this->personPick;
        $this->success = 'Evidence recorded successfully.';
        $this->fieldName = '';
        $this->fieldValue = '';
    }

    public function render()
    {
        $evidence = ($this->businessId > 0 && $this->personId > 0)
            ? ResolutionEvidence::where('business_id', $this->businessId)->where('canonical_person_id', $this->personId)->get()
            : collect();

        $people = [];
        if ($this->businessId > 0) {
            $people = app(PersonLookupAction::class)->listForBusiness(Tenancy::idOrFail());
        }

        return view('x-132::person-timeline', [
            'evidence' => $evidence,
            'people' => $people,
        ]);
    }
}
