<?php

declare(strict_types=1);

namespace App\Modules\X136\Ui;

use App\Modules\X136\Actions\SignalListAction;
use App\Modules\X136\Actions\SignalScoreAction;
use App\Modules\X136\Models\SignalScore;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Cooling Signals'])]
class CoolingView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public ?string $actionFailed = null;

    public string $prospectIdentifier = '';

    public string $signalType = '';

    public string $signalScore = '';

    public ?string $error = null;

    public ?string $success = null;

    public function mount(): void
    {
        if ($this->businessId === 0) {
            $this->businessId = Tenancy::id() ?: 0;
            if ($this->businessId <= 0) {
                abort(403, 'Tenant context is required');
            }
        }
    }

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
    }

    public function recordSignal(SignalScoreAction $action): void
    {
        if ($this->isSample) {
            return;
        }

        $this->error = null;
        $this->success = null;

        if (trim($this->prospectIdentifier) === '') {
            $this->error = 'Enter who the signal is about.';

            return;
        }

        if (trim($this->signalType) === '') {
            $this->error = 'Enter the signal type, such as pricing_visit.';

            return;
        }

        if (trim($this->signalScore) === '' || ! is_numeric(trim($this->signalScore))) {
            $this->error = 'Enter the score as a number.';

            return;
        }

        $type = trim($this->signalType);

        $score = $action->recordAndScore(
            Tenancy::idOrFail(),
            trim($this->prospectIdentifier),
            $type,
            [],
            (float) $this->signalScore
        );

        $this->success = $score->cooling_status === 'cooling'
            ? "Recorded {$type} for {$score->prospect_identifier} at {$score->signal_value} — cooling, so it is listed below."
            : "Recorded {$type} for {$score->prospect_identifier} at {$score->signal_value} — fresh, so it is not on this list; the signal volume screen counts it.";

        $this->prospectIdentifier = '';
        $this->signalType = '';
        $this->signalScore = '';
    }

    public function markDecayed(string $prospectIdentifier, SignalListAction $action): void
    {
        if ($this->isSample) {
            return;
        }

        $this->actionFailed = null;

        try {
            $score = SignalScore::where('business_id', $this->businessId)
                ->where('prospect_identifier', $prospectIdentifier)
                ->first();

            if (! $score) {
                throw new \DomainException('Unknown prospect identifier');
            }

            $days = $score->updated_at ? (int) $score->updated_at->diffInDays(now()) : 0;
            $action->markDecayed($this->businessId, $prospectIdentifier, $days);
        } catch (\Exception $e) {
            $this->actionFailed = $e->getMessage();
        }
    }

    public function render()
    {
        if ($this->isSample) {
            $scores = collect([
                (object) ['prospect_identifier' => 'acme-roofing', 'cooling_status' => 'cooling', 'signal_value' => 60.5, 'signal_type' => 'pricing_visit', 'days_quiet' => 2],
                (object) ['prospect_identifier' => 'northside-dental', 'cooling_status' => 'cooling', 'signal_value' => 45.0, 'signal_type' => 'hiring', 'days_quiet' => 5],
            ]);
            $coolingTotal = 2;
        } else {
            $scores = collect();
            $coolingTotal = 0;
            if ($this->businessId > 0) {
                $query = SignalScore::query()
                    ->join('signals', 'signal_scores.signal_id', '=', 'signals.id')
                    ->where('signal_scores.business_id', $this->businessId)
                    ->where('cooling_status', 'cooling')
                    ->select('signal_scores.*', 'signals.signal_type')
                    ->orderBy('signal_scores.id', 'desc');

                $scores = $query->get()->map(function ($score) {
                    $score->days_quiet = $score->updated_at ? (int) $score->updated_at->diffInDays(now()) : 0;

                    return $score;
                });
                $coolingTotal = $scores->count();
            }
        }

        return view('x-136::cooling', [
            'scores' => $scores,
            'coolingTotal' => $coolingTotal,
        ]);
    }
}
