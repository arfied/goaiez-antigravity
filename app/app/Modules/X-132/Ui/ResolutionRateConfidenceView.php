<?php

declare(strict_types=1);

namespace App\Modules\X132\Ui;

use App\Enums\UserRole;
use App\Modules\X121\Actions\PersonLookupAction;
use App\Modules\X132\Actions\PersonMergeAction;
use App\Modules\X132\Models\PersonLink;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Resolution rate & confidence'])]
class ResolutionRateConfidenceView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public int $canonicalId = 0;

    public int $duplicateId = 0;

    public string $error = '';

    public string $success = '';

    public function mount()
    {
        abort_unless(auth()->check() && (auth()->user()->hasRole(UserRole::Owner, UserRole::Manager)), 403);
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function linkPeople(PersonMergeAction $action): void
    {
        $this->error = '';
        $this->success = '';

        if ($this->canonicalId === 0 || $this->duplicateId === 0) {
            $this->error = 'Please select both a canonical person and a duplicate person.';

            return;
        }

        if ($this->canonicalId === $this->duplicateId) {
            $this->error = 'Cannot link a person to themselves.';

            return;
        }

        $action->merge(Tenancy::idOrFail(), $this->canonicalId, $this->duplicateId);

        $this->success = 'The two people are now recorded as the same person. This records the link only and does not delete or alter the duplicate person.';
        $this->canonicalId = 0;
        $this->duplicateId = 0;
    }

    public function render()
    {
        $links = ($this->businessId > 0)
            ? PersonLink::where('business_id', $this->businessId)->get()
            : collect();

        $people = [];
        if ($this->businessId > 0) {
            $people = app(PersonLookupAction::class)->listForBusiness(Tenancy::idOrFail());
        }

        return view('x-132::resolution-rate-confidence', [
            'links' => $links,
            'people' => $people,
        ]);
    }
}
