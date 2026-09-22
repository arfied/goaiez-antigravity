<?php

declare(strict_types=1);

namespace App\Modules\X149\Ui;

use App\Modules\X149\Actions\TrackQualitySeriesAction;
use App\Modules\X149\Models\QualitySeries;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class QualityBoard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $currentRefusalRate = '';

    public string $baselineRefusalRate = '';

    public ?string $error = null;

    public ?string $success = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function recordQuality(TrackQualitySeriesAction $action): void
    {
        if ($this->currentRefusalRate === '' || $this->baselineRefusalRate === '' || ! is_numeric($this->currentRefusalRate) || ! is_numeric($this->baselineRefusalRate)) {
            $this->error = 'Valid rates are required.';

            return;
        }

        $this->error = null;
        $series = $action->record(Tenancy::idOrFail(), (float) $this->currentRefusalRate, (float) $this->baselineRefusalRate);

        $this->success = 'Recorded quality series. Anomaly detected: '.($series->anomaly_detected ? $series->event_name : 'No').'. This feeds the quality lists; nothing downstream is wired to it yet.';
        $this->currentRefusalRate = '';
        $this->baselineRefusalRate = '';
    }

    public function render()
    {
        $series = ($this->businessId > 0)
            ? QualitySeries::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-149::quality-board', [
            'series' => $series,
        ]);
    }
}
