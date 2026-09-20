<?php

declare(strict_types=1);

namespace App\Modules\X07\Ui;

use App\Modules\X07\Models\Forecast;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your forecast'])]
class ForecastRiskTiles extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $periodMonth = '';
    public string $riskScore = '';
    public ?string $success = null;
    public ?string $error = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function submit(\App\Modules\X07\Actions\ChurnScoreAction $action): void
    {
        $this->reset(['success', 'error']);

        if (empty($this->periodMonth)) {
            $this->error = 'Period month is required.';
            return;
        }

        if ($this->riskScore === '') {
            $this->error = 'Risk score is required.';
            return;
        }

        $risk = (int) $this->riskScore;
        if ($risk < 0 || $risk > 100) {
            $this->error = 'Risk score must be between 0 and 100.';
            return;
        }

        $res = $action->evaluateRisk(Tenancy::idOrFail(), $this->periodMonth, $risk);
        
        $highRiskMsg = $res['is_high_risk'] ? 'high-risk' : 'low-risk';
        $this->success = "Recorded {$highRiskMsg} forecast. This feeds the forecast tiles; nothing downstream is wired to it yet.";
        $this->reset(['periodMonth', 'riskScore']);
    }

    public function render()
    {
        $forecasts = ($this->businessId > 0)
            ? Forecast::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-07::forecast-risk-tiles', [
            'forecasts' => $forecasts,
        ]);
    }
}
