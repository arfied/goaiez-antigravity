<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Ui;

use App\Modules\CBilling\Actions\DunningAdvanceAction;
use App\Modules\CBilling\Models\DunningState;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Dunning board'])]
class DunningBoard extends Component
{
    use LabelsDunning;

    public ?string $error = null;

    public function advance(int $stateId, DunningAdvanceAction $action): void
    {
        $this->error = null;
        try {
            $state = DunningState::where('business_id', Tenancy::idOrFail())->findOrFail($stateId);
            $action->handle(Tenancy::idOrFail(), $state->day_in_cycle + 1);
        } catch (ModelNotFoundException) {
            $this->error = "That case isn't in this account any more.";
        } catch (\Throwable $e) {
            $this->error = 'Failed to advance dunning: '.$e->getMessage();
        }
    }

    public function render()
    {
        abort_unless(auth()->check() && Tenancy::check(), 403);

        $states = DunningState::where('business_id', Tenancy::id())
            ->orderBy('day_in_cycle', 'desc')
            ->get();

        foreach ($states as $state) {
            $state->next_step_words = $this->getNextStepWords($state->day_in_cycle);
        }

        return view('c-billing::dunning-board', [
            'states' => $states,
            'dunningLabels' => $this->dunningLabels(),
            'dunningPillStates' => $this->dunningPillStates(),
        ]);
    }

    private function getNextStepWords(int $day): string
    {
        if ($day < 7) {
            return 'day 7 human';
        }
        if ($day < 21) {
            return 'day 21 pause with the phone answering';
        }
        if ($day < 30) {
            return 'day 30 calls stop';
        }
        if ($day < 60) {
            return 'day 60 number released';
        }

        return 'data never deleted';
    }
}
