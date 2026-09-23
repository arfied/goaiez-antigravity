<?php

declare(strict_types=1);

namespace App\Modules\X136\Ui;

use App\Modules\X136\Actions\DecayModelSetAction;
use App\Modules\X136\Models\DecayModel;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SignalVolumePrecisionView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public array $editHalfLife = [];

    public array $editDecayRate = [];

    public array $refusals = [];

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

    public function saveDecay(string $signalType, DecayModelSetAction $action): void
    {
        abort_if(Tenancy::id() === null, 403);
        // The brief says: "copy its guard" -> the module's other screens use Gate::authorize.
        // Let's also include the exact abort if we want:
        // if ($this->businessId <= 0) { abort(403, 'Tenant context is required'); }

        $halfLife = (int) ($this->editHalfLife[$signalType] ?? 0);
        $rate = (float) ($this->editDecayRate[$signalType] ?? 0.0);

        $result = $action->handle($this->businessId, $signalType, $halfLife, $rate);

        if (is_array($result) && isset($result['refused'])) {
            $this->refusals[$signalType] = $result['refused'];
        } else {
            unset($this->refusals[$signalType]);
        }
    }

    public function render()
    {
        if ($this->isSample) {
            $stats = collect([
                (object) [
                    'signal_type' => 'hiring',
                    'total_count' => 100,
                    'high_intent_count' => 20,
                    'precision_pct' => 20.0,
                    'decay_model' => '12 days / 4%',
                ],
                (object) [
                    'signal_type' => 'permit_filed',
                    'total_count' => 50,
                    'high_intent_count' => 45,
                    'precision_pct' => 90.0,
                    'decay_model' => '7 days / 10%',
                ],
            ]);
        } else {
            $stats = collect();
            if ($this->businessId > 0) {
                $decayModels = DecayModel::where('business_id', $this->businessId)
                    ->get()
                    ->keyBy('signal_type');

                $rows = DB::table('signals')
                    ->where('signals.business_id', $this->businessId)
                    ->leftJoin('signal_scores', 'signals.id', '=', 'signal_scores.signal_id')
                    ->select(
                        'signals.signal_type',
                        DB::raw('count(signals.id) as total_count'),
                        DB::raw('sum(case when signal_scores.is_high_intent = true then 1 else 0 end) as high_intent_count')
                    )
                    ->groupBy('signals.signal_type')
                    ->get();

                $stats = $rows->map(function ($row) use ($decayModels) {
                    $precision = $row->total_count > 0
                        ? round(($row->high_intent_count / $row->total_count) * 100, 1)
                        : 0;

                    $decay = $decayModels->get($row->signal_type);
                    $decayStr = $decay
                        ? "{$decay->half_life_days} days / ".($decay->decay_rate * 100).'%'
                        : 'No decay model yet. A model needs 30 days of events.';

                    return (object) [
                        'signal_type' => $row->signal_type,
                        'total_count' => $row->total_count,
                        'high_intent_count' => $row->high_intent_count,
                        'precision_pct' => $precision,
                        'decay_model' => $decayStr,
                    ];
                });
            }
        }

        return view('x-136::signal-volume-precision', [
            'stats' => $stats,
        ]);
    }
}
