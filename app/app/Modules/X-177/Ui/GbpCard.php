<?php

declare(strict_types=1);

namespace App\Modules\X177\Ui;

use App\Modules\X177\Actions\GbpStateAction;
use App\Modules\X177\Models\GbpConnection;
use App\Modules\X177\Models\GbpPost;
use App\Modules\X177\Models\GbpStateLog;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Google profile'])]
class GbpCard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public ?int $viewingLogId = null;

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
        $this->viewingLogId = null;
    }

    public function toggleLog(int $connectionId): void
    {
        if ($this->viewingLogId === $connectionId) {
            $this->viewingLogId = null;
        } else {
            $this->viewingLogId = $connectionId;
        }
    }

    public function pollState(int $connectionId): void
    {
        if ($this->isSample) {
            return;
        }
        Tenancy::set($this->businessId);

        $action = app(GbpStateAction::class);
        $action->pollState($this->businessId, $connectionId);
    }

    public function render()
    {
        Tenancy::set($this->businessId);

        $connections = collect();
        if (! $this->isSample) {
            $connections = GbpConnection::where('business_id', $this->businessId)->get()->map(function ($c) {
                $c->latest_post = GbpPost::where('business_id', $this->businessId)
                    ->where('connection_id', $c->id)
                    ->latest('created_at')
                    ->first();

                $c->latest_log = GbpStateLog::where('business_id', $this->businessId)
                    ->where('connection_id', $c->id)
                    ->latest('created_at')
                    ->first();

                $c->plain_status = $c->profile_status === 'suspended' ? 'Profile is suspended' : 'We are not reading your profile’s status yet';

                return $c;
            });
        } else {
            $connections = collect([
                (object) [
                    'id' => 999,
                    'external_label' => 'Sample Location',
                    'profile_status' => 'suspended',
                    'plain_status' => 'Profile is suspended',
                    'latest_post' => (object) ['content' => 'Summer sale started today'],
                    'latest_log' => (object) ['event_type' => 'state_read', 'details' => ['old' => 'active', 'new' => 'suspended'], 'created_at' => now()],
                ],
            ]);
        }

        $isEmpty = ! $this->isSample && $connections->isEmpty();

        return view('x-177::gbp-card', [
            'connections' => $connections,
            'isEmpty' => $isEmpty,
        ]);
    }
}
