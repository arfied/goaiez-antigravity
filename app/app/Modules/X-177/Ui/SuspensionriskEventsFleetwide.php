<?php

declare(strict_types=1);

namespace App\Modules\X177\Ui;

use App\Modules\X177\Actions\GbpStateAction;
use App\Modules\X177\Models\GbpConnection;
use App\Modules\X177\Models\GbpPost;
use App\Modules\X177\Models\GbpStateLog;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * (R245) Suspension-risk events screen reads the current tenant under RLS; a cross-tenant fleet view needs a platform-scoped read that does not exist (same shape as the businesses-policy refusal); listed UNRESOLVED, not faked.
 */
#[Layout('components.account.layout', ['heading' => 'Suspension risks'])]
class SuspensionriskEventsFleetwide extends Component
{
    #[Locked]
    public int $businessId = 0;

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

    public bool $isSample = false;

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
    }

    public function pollState(int $connectionId): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);
        app(GbpStateAction::class)->pollState($this->businessId, $connectionId);
    }

    public function render()
    {
        Tenancy::set($this->businessId);

        $events = collect();

        if ($this->isSample) {
            $events = collect([
                (object) [
                    'type' => 'post',
                    'status' => 'rejected_risk',
                    'label' => 'Sample Location 1',
                    'content' => 'Sample flagged post content here...',
                    'created_at' => now()->subHours(1),
                    'connection_id' => 9991,
                ],
                (object) [
                    'type' => 'log',
                    'status' => 'suspended',
                    'label' => 'Sample Location 2',
                    'content' => 'active → suspended',
                    'created_at' => now()->subHours(2),
                    'connection_id' => 9992,
                ],
            ]);
        } else {
            $posts = GbpPost::where('business_id', $this->businessId)
                ->whereIn('status', ['rejected_risk', 'blocked_by_suspension'])
                ->get()
                ->map(function (GbpPost $post): object {
                    $conn = GbpConnection::find($post->connection_id);

                    return (object) [
                        'type' => 'post',
                        'status' => (string) $post->status,
                        'label' => (string) ($conn ? ($conn->external_label ?? $conn->location_id) : 'Unknown'),
                        'content' => Str::limit((string) $post->content, 80),
                        'created_at' => Carbon::parse($post->created_at),
                        'connection_id' => (int) $post->connection_id,
                    ];
                });

            $logs = GbpStateLog::where('business_id', $this->businessId)
                ->where('details->new_status', 'suspended')
                ->get()
                ->map(function (GbpStateLog $log): object {
                    $conn = GbpConnection::find($log->connection_id);
                    $old = $log->details['old_status'] ?? 'unknown';
                    $new = $log->details['new_status'] ?? 'unknown';

                    return (object) [
                        'type' => 'log',
                        'status' => 'suspended',
                        'label' => (string) ($conn ? ($conn->external_label ?? $conn->location_id) : 'Unknown'),
                        'content' => "$old → $new",
                        'created_at' => Carbon::parse($log->created_at),
                        'connection_id' => (int) $log->connection_id,
                    ];
                });

            $events = $posts->concat($logs)->sortByDesc('created_at')->values();
        }

        return view('x-177::suspensionrisk-events-fleetwide', [
            'events' => $events,
        ]);
    }
}
