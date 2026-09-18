<?php

declare(strict_types=1);

namespace App\Modules\X156\Ui;

use App\Modules\X156\Actions\IngestSourcePauseAction;
use App\Modules\X156\Models\IngestRejection;
use App\Modules\X156\Models\IngestRun;
use App\Modules\X156\Models\IngestSource;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Data coming in'])]
class IngestVolumeByView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public bool $actionFailed = false;

    public function mount(): void
    {
        if ($this->businessId === 0) {
            $tenantId = Tenancy::id() ?: 0;
            if ($tenantId <= 0) {
                abort(403, 'Tenant context is required');
            }
            $this->businessId = (int) $tenantId;
        }
        Tenancy::set($this->businessId);
    }

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
        $this->actionFailed = false;
    }

    public function pause(int $id): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);
        try {
            app(IngestSourcePauseAction::class)->handle($this->businessId, $id, false);
            $this->actionFailed = false;
        } catch (\Exception $e) {
            $this->actionFailed = true;
        }
    }

    public function resume(int $id): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);
        try {
            app(IngestSourcePauseAction::class)->handle($this->businessId, $id, true);
            $this->actionFailed = false;
        } catch (\Exception $e) {
            $this->actionFailed = true;
        }
    }

    public function render()
    {
        Tenancy::set($this->businessId);

        if ($this->isSample) {
            $sources = collect([
                (object) [
                    'id' => 9501,
                    'source_name' => 'HubSpot CRM',
                    'source_type' => 'hubspot',
                    'is_active' => true,
                    'runs_count' => 12,
                    'records_total' => 1500,
                    'last_run_at' => now()->subHours(2),
                    'rejections_count' => 0,
                ],
                (object) [
                    'id' => 9502,
                    'source_name' => 'Meta lead forms',
                    'source_type' => 'meta_lead_ad',
                    'is_active' => false,
                    'runs_count' => 5,
                    'records_total' => 120,
                    'last_run_at' => now()->subDay(),
                    'rejections_count' => 3,
                ],
            ]);
            $tenantTotalRecords = 1620;
            $tenantTotalRuns = 17;
        } else {
            $dbSources = IngestSource::where('business_id', $this->businessId)->orderByDesc('id')->get();
            $sources = $dbSources->map(function (IngestSource $s): object {
                $runs = IngestRun::where('business_id', $this->businessId)->where('source_id', $s->id)->get();
                $lastRun = $runs->sortByDesc('id')->first();
                $runsCount = $runs->count();
                $recordsTotal = (int) $runs->sum('records_ingested');

                $rejections = IngestRejection::where('business_id', $this->businessId)
                    ->where('source_id', $s->id)
                    ->count();

                return (object) [
                    'id' => (int) $s->id,
                    'source_name' => (string) $s->source_name,
                    'source_type' => (string) $s->source_type,
                    'is_active' => (bool) $s->is_active,
                    'runs_count' => $runsCount,
                    'records_total' => $recordsTotal,
                    'last_run_at' => $lastRun ? Carbon::parse($lastRun->created_at) : null,
                    'rejections_count' => $rejections,
                ];
            });
            $allRuns = IngestRun::where('business_id', $this->businessId)->get();
            $tenantTotalRecords = (int) $allRuns->sum('records_ingested');
            $tenantTotalRuns = $allRuns->count();
        }

        $sourceTypeLabels = [
            'salesforce' => 'Salesforce',
            'hubspot' => 'HubSpot',
            'ghl' => 'GoHighLevel',
            'meta_lead_ad' => 'Meta lead forms',
            'drive' => 'Google Drive',
            'notion' => 'Notion',
            'hcp' => 'Housecall Pro',
            'jobber' => 'Jobber',
            'qr_scan' => 'Badge / QR scan',
        ];

        return view('x-156::ingest-volume-by', [
            'sources' => $sources,
            'sourceTypeLabels' => $sourceTypeLabels,
            'tenantTotalRecords' => $tenantTotalRecords,
            'tenantTotalRuns' => $tenantTotalRuns,
        ]);
    }
}
