<?php

declare(strict_types=1);

namespace App\Modules\X156\Ui;

use App\Modules\X156\Actions\IngestConnectAction;
use App\Modules\X156\Actions\IngestSourcePauseAction;
use App\Modules\X156\Models\IngestRejection;
use App\Modules\X156\Models\IngestRun;
use App\Modules\X156\Models\IngestSource;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Connect a source'])]
class ConnectSourceView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public ?string $actionNotice = null;

    public string $sourceType = 'hubspot';

    public string $sourceName = '';

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
        $this->actionNotice = null;
    }

    public function connect(): void
    {
        if ($this->isSample) {
            return;
        }

        $this->validate([
            'sourceType' => 'required|in:salesforce,hubspot,ghl,meta_lead_ad,drive,notion,hcp,jobber,qr_scan',
            'sourceName' => 'required|string|max:120',
        ]);

        Tenancy::set($this->businessId);

        try {
            app(IngestConnectAction::class)->connect($this->businessId, $this->sourceType, $this->sourceName);
            $this->actionNotice = 'Connected '.$this->sourceName;
            $this->sourceName = '';
        } catch (\Exception $e) {
            $this->actionNotice = $e->getMessage();
        }
    }

    public function pause(int $id): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);
        try {
            app(IngestSourcePauseAction::class)->handle($this->businessId, $id, false);
            $this->actionNotice = null;
        } catch (\Exception $e) {
            $this->actionNotice = $e->getMessage();
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
            $this->actionNotice = null;
        } catch (\Exception $e) {
            $this->actionNotice = $e->getMessage();
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
                    'last_run_records' => 128,
                    'last_run_time' => now()->subHours(2),
                    'rejections_count' => 0,
                ],
                (object) [
                    'id' => 9502,
                    'source_name' => 'Meta lead forms',
                    'source_type' => 'meta_lead_ad',
                    'is_active' => false,
                    'last_run_records' => 12,
                    'last_run_time' => now()->subDay(),
                    'rejections_count' => 3,
                ],
            ]);
        } else {
            $dbSources = IngestSource::where('business_id', $this->businessId)->orderByDesc('id')->get();
            $sources = $dbSources->map(function (IngestSource $s): object {
                $lastRun = IngestRun::where('business_id', $this->businessId)
                    ->where('source_id', $s->id)
                    ->latest('id')
                    ->first();

                $rejections = IngestRejection::where('business_id', $this->businessId)
                    ->where('source_id', $s->id)
                    ->count();

                return (object) [
                    'id' => (int) $s->id,
                    'source_name' => (string) $s->source_name,
                    'source_type' => (string) $s->source_type,
                    'is_active' => (bool) $s->is_active,
                    'last_run_records' => $lastRun ? (int) $lastRun->records_ingested : null,
                    'last_run_time' => $lastRun ? Carbon::parse($lastRun->created_at) : null,
                    'rejections_count' => $rejections,
                ];
            });
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

        return view('x-156::connect-source', [
            'sources' => $sources,
            'sourceTypeLabels' => $sourceTypeLabels,
        ]);
    }
}
