<?php

declare(strict_types=1);

namespace App\Modules\X156\Ui;

use App\Modules\X156\Actions\IngestSourcePauseAction;
use App\Modules\X156\Models\IngestRejection;
use App\Modules\X156\Models\IngestSource;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Rows we could not take'])]
class RejectedrowsListView extends Component
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

    public function pause(int $sourceId): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);
        try {
            app(IngestSourcePauseAction::class)->handle($this->businessId, $sourceId, false);
            $this->actionFailed = false;
        } catch (\Exception $e) {
            $this->actionFailed = true;
        }
    }

    public function resume(int $sourceId): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);
        try {
            app(IngestSourcePauseAction::class)->handle($this->businessId, $sourceId, true);
            $this->actionFailed = false;
        } catch (\Exception $e) {
            $this->actionFailed = true;
        }
    }

    public function render()
    {
        Tenancy::set($this->businessId);

        if ($this->isSample) {
            $rejections = collect([
                (object) [
                    'id' => 8501,
                    'rejection_reason' => 'Every contact write carries an attestation_id or is refused (P-069)',
                    'source_name' => 'HubSpot CRM',
                    'source_id' => 9501,
                    'signature_verified' => true,
                    'is_active' => true,
                    'created_at' => now()->subMinutes(15),
                    'record_count' => 1,
                ],
                (object) [
                    'id' => 8502,
                    'rejection_reason' => 'HMAC signature verification failed',
                    'source_name' => 'Unknown source',
                    'source_id' => null,
                    'signature_verified' => false,
                    'is_active' => false,
                    'created_at' => now()->subHours(2),
                    'record_count' => null,
                ],
            ]);
        } else {
            $dbRejections = IngestRejection::where('business_id', $this->businessId)
                ->orderByDesc('id')
                ->get();

            $sources = IngestSource::where('business_id', $this->businessId)->get()->keyBy('id');

            $rejections = $dbRejections->map(function (IngestRejection $r) use ($sources): object {
                $payload = is_array($r->raw_payload) ? $r->raw_payload : (is_string($r->raw_payload) ? json_decode($r->raw_payload, true) : null);
                $recordCount = is_array($payload) && array_key_exists('record_count', $payload) ? $payload['record_count'] : null;

                $source = $r->source_id ? $sources->get($r->source_id) : null;

                return (object) [
                    'id' => (int) $r->id,
                    'rejection_reason' => (string) $r->rejection_reason,
                    'source_name' => $source ? (string) $source->source_name : 'Unknown source',
                    'source_id' => $r->source_id ? (int) $r->source_id : null,
                    'signature_verified' => (bool) $r->signature_verified,
                    'is_active' => $source ? (bool) $source->is_active : false,
                    'created_at' => Carbon::parse($r->created_at),
                    'record_count' => $recordCount,
                ];
            });
        }

        return view('x-156::rejectedrows-list', [
            'rejections' => $rejections,
        ]);
    }
}
