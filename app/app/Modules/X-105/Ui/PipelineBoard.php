<?php

declare(strict_types=1);

namespace App\Modules\X105\Ui;

use App\Modules\X105\Actions\DemoRequestAction;
use App\Modules\X105\Actions\OutreachHaltAction;
use App\Modules\X105\Models\OutreachLadder;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PipelineBoard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public bool $actionFailed = false;

    public function mount(): void
    {
        if ($this->businessId === 0) {
            $this->businessId = Tenancy::id() ?: 0;
            if ($this->businessId <= 0) {
                abort(403, 'Tenant context is required');
            }
        }
        Tenancy::set($this->businessId);
    }

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
        $this->actionFailed = false;
    }

    public function halt(int $id, OutreachHaltAction $action): void
    {
        if ($this->isSample) {
            return;
        }

        $this->actionFailed = false;

        $ladder = OutreachLadder::where('business_id', $this->businessId)->find($id);

        if (! $ladder) {
            $this->actionFailed = true;

            return;
        }

        $action->halt($this->businessId, $ladder->id);
    }

    public function requestDemo(int $id, string $preferredTime, DemoRequestAction $action): void
    {
        if ($this->isSample) {
            return;
        }

        $this->actionFailed = false;

        $ladder = OutreachLadder::where('business_id', $this->businessId)->find($id);

        if (! $ladder) {
            $this->actionFailed = true;

            return;
        }

        $action->request($this->businessId, $ladder->id, $preferredTime);
    }

    public function render()
    {
        if ($this->isSample) {
            $ladders = collect([
                (object) [
                    'id' => 9999, // identifier no fixture uses
                    'status' => 'active',
                    'person_id' => 1234,
                    'person' => (object) ['id' => 1234, 'name' => 'Sample Person'],
                    'steps' => collect([
                        (object) ['status' => 'completed'],
                        (object) ['status' => 'completed'],
                        (object) ['status' => 'pending', 'type' => 'email', 'scheduled_at' => now()->addDay()],
                    ]),
                    'updated_at' => now()->subHours(2),
                ],
            ]);
        } else {
            $ladders = OutreachLadder::where('business_id', $this->businessId)
                ->with('steps')
                ->get();
        }

        return view('x-105::pipeline-board', [
            'ladders' => $ladders,
        ]);
    }
}
