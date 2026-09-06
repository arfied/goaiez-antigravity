<?php

declare(strict_types=1);

namespace App\Modules\X171\Ui;

use App\Enums\UserRole;
use App\Modules\X171\Actions\ReplayOfflineSyncAction;
use App\Modules\X171\Models\DeviceSyncConflict;
use App\Modules\X171\Models\DeviceSyncQueue;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SyncFailureRate extends Component
{
    #[Locked]
    public int $businessId;

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);
        $this->businessId = Tenancy::id();
    }

    public function keepDevice(int $conflictId)
    {
        $conflict = DeviceSyncConflict::where('business_id', $this->businessId)->findOrFail($conflictId);
        $queue = DeviceSyncQueue::where('business_id', $this->businessId)->findOrFail($conflict->queue_id);

        app(ReplayOfflineSyncAction::class)->replayMutation(
            $this->businessId,
            $queue->client_mutation_id.'_replayed',
            $queue->device_id,
            $queue->action_name,
            $queue->payload,
            $conflict->server_version + 1,
            $conflict->server_version
        );

        $conflict->conflict_reason = 'Resolved: keep_device';
        $conflict->save();
    }

    public function render()
    {
        $total = DeviceSyncQueue::where('business_id', $this->businessId)->count();
        $conflicted = DeviceSyncQueue::where('business_id', $this->businessId)->where('status', 'conflicted')->count();

        $ratePct = $total > 0 ? ($conflicted / $total) * 100 : null;
        $rate = $ratePct === null ? null : number_format($ratePct, 1, '.', '').' %';
        $score = $ratePct === null ? null : (int) round(100 - $ratePct);
        $sentence = $total > 0 ? "{$conflicted} of {$total} device mutations conflicted" : 'No device mutations yet.';

        $conflicts = DeviceSyncConflict::where('business_id', $this->businessId)->orderByDesc('id')->get();

        $versions = [];
        $pill = [];

        foreach ($conflicts as $c) {
            $versions[$c->id] = 'v'.$c->client_version.' → v'.$c->server_version;
            if (str_starts_with($c->conflict_reason, 'Resolved:')) {
                $pill[$c->id] = ['ok', 'Resolved'];
            } else {
                $pill[$c->id] = ['attention', 'Open'];
            }
        }

        return view('x-171::sync-failure-rate', [
            'total' => $total,
            'conflicted' => $conflicted,
            'ratePct' => $ratePct,
            'rate' => $rate,
            'score' => $score,
            'sentence' => $sentence,
            'conflicts' => $conflicts,
            'versions' => $versions,
            'pill' => $pill,
        ]);
    }
}
