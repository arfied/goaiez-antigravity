<?php

declare(strict_types=1);

namespace App\Modules\X131\Ui;

use App\Modules\X121\Actions\EntityReadAction;
use App\Modules\X131\Models\PersonInterest;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'What your customers are interested in'])]
class InterestTagsView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $personId = '';
    public string $topic = '';
    public ?string $success = null;
    public ?string $error = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function submit(\App\Modules\X131\Actions\InterestSetAction $action): void
    {
        $this->reset(['success', 'error']);

        $personIdInt = (int) $this->personId;
        if ($personIdInt === 0) {
            $this->error = 'Person ID is required and cannot be 0.';
            return;
        }

        if (empty($this->topic)) {
            $this->error = 'Topic is required.';
            return;
        }

        $interest = $action->set(Tenancy::idOrFail(), $personIdInt, $this->topic);
        $this->success = "Recorded interest '{$interest->topic}' for customer #{$personIdInt}. It is protected from being overwritten by inference. Nothing downstream is wired to it yet.";
        $this->reset(['personId', 'topic']);
    }

    public function render()
    {
        if ($this->businessId <= 0) {
            return view('x-131::interest-tags', ['interests' => collect(), 'people' => []]);
        }

        $interests = PersonInterest::where('business_id', $this->businessId)
            ->orderBy('person_id')
            ->orderByDesc('is_tenant_set')
            ->orderByDesc('confidence_rate')
            ->get();

        $people = [];
        $action = app(EntityReadAction::class);
        foreach ($interests->pluck('person_id')->unique() as $pId) {
            $row = $action->handle('people', (int) $pId, $this->businessId);
            if ($row !== null) {
                $people[(int) $pId] = $row['first_name'].' '.$row['last_name'];
            }
        }

        return view('x-131::interest-tags', [
            'interests' => $interests,
            'people' => $people,
        ]);
    }
}
