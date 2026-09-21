<?php

declare(strict_types=1);

namespace App\Modules\X127\Ui;

use App\Modules\X127\Actions\TenantzeroMetricAction;
use App\Modules\X127\Models\PublishedMetric;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MetricProofPanel extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $metricKey = '';

    public string $publishedValue = '';

    public string $liveQuery = '';

    public ?string $error = null;

    public ?string $success = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function recordMetric(TenantzeroMetricAction $action): void
    {
        if (empty($this->metricKey) || empty($this->publishedValue) || empty($this->liveQuery)) {
            $this->error = 'All fields are required.';

            return;
        }

        $this->error = null;
        $metric = $action->handle(Tenancy::idOrFail(), $this->metricKey, $this->publishedValue, $this->liveQuery);

        $this->success = 'Recorded metric. The live query is stored for proof; nothing runs it yet. Note that if a metric already exists, it is updated rather than duplicated.';
        $this->metricKey = '';
        $this->publishedValue = '';
        $this->liveQuery = '';
    }

    public function render()
    {
        $metrics = ($this->businessId > 0)
            ? PublishedMetric::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-127::metric-proof-panel', [
            'metrics' => $metrics,
        ]);
    }
}
