<?php

declare(strict_types=1);

namespace App\Modules\X127\Ui;

use App\Modules\X127\Actions\TenantzeroMetricAction;
use App\Modules\X127\Actions\TenantzeroProofAction;
use App\Modules\X127\Models\PublishedMetric;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Metric proof panel'])]
class MetricProofPanel extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $metricKey = '';

    public string $publishedValue = '';

    public string $liveQuery = '';

    public ?string $error = null;

    public ?string $success = null;

    public string $verifyKey = '';

    public string $verifyLiveValue = '';

    public ?string $verifyError = null;

    public ?string $verifySuccess = null;

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

    public function verifyMetric(TenantzeroProofAction $action): void
    {
        if (trim($this->verifyKey) === '') {
            $this->verifyError = 'Choose the metric to check.';

            return;
        }

        if (trim($this->verifyLiveValue) === '') {
            $this->verifyError = 'Enter the value you measured just now.';

            return;
        }

        $result = $action->handle(Tenancy::idOrFail(), trim($this->verifyKey), trim($this->verifyLiveValue));

        $this->verifySuccess = match (true) {
            $result['status'] === 'verified' => 'Metric '.$result['metric_key'].' checks out at '.$result['value'].'. The published claim matches the live value.',
            ($result['previous_claim'] ?? null) === null => 'Metric '.$result['metric_key'].' has no published claim to check — it was pulled earlier. Live value now: '.$result['live_value'].'. Publish a new claim before checking again.',
            default => 'Metric '.$result['metric_key'].' did NOT match — published '.$result['previous_claim'].', live '.$result['live_value'].'. The claim has been PULLED from publication.',
        };

        $this->verifyError = null;
        $this->verifyKey = '';
        $this->verifyLiveValue = '';
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
