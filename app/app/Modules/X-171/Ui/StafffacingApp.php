<?php

declare(strict_types=1);

namespace App\Modules\X171\Ui;

use App\Enums\UserRole;
use App\Modules\X171\Actions\BarcodeScanAction;
use App\Modules\X171\Actions\JobPhotoAction;
use App\Modules\X171\Actions\JobSignAction;
use App\Modules\X171\Actions\JobStateAction;
use App\Modules\X171\Actions\NoteVoiceAction;
use App\Modules\X171\Actions\ReplayOfflineSyncAction;
use App\Modules\X171\Models\DeviceSyncConflict;
use App\Modules\X171\Models\DeviceSyncQueue;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StafffacingApp extends Component
{
    #[Locked]
    public $deviceId = 'device_default'; // the default until the app sends a device id

    public $errorMessage = null;

    public $scanInput = [];

    public $signatureInput = [];

    public $photoInput = [];

    public $voiceInput = [];

    public function mount()
    {
        abort_unless(auth()->check() && (auth()->user()->hasRole(UserRole::Staff, UserRole::SuperAdmin) || auth()->user()->hasRole(UserRole::Owner, UserRole::Manager)), 403);
        $businessId = Tenancy::id();
        abort_unless($businessId !== null && $businessId > 0, 403);
    }

    public function tap($jobId = null, $newState = null)
    {
        $this->errorMessage = null;
        try {
            $businessId = Tenancy::id();
            $techId = auth()->id();
            $action = app(JobStateAction::class);
            $action->updateState($businessId, $jobId, $techId, $newState, 1);
        } catch (\Exception $e) {
            $this->errorMessage = 'Could not update job state.';
        }
    }

    public function scan(int $jobId)
    {
        $payload = $this->scanInput[$jobId] ?? '';
        if (empty($payload)) {
            $this->errorMessage = 'Scan a barcode first.';

            return;
        }
        try {
            $res = app(BarcodeScanAction::class)->handle(Tenancy::id(), $jobId, $payload);
            $this->errorMessage = null;
        } catch (\Exception $e) {
            $this->errorMessage = 'Could not scan barcode.';
        }
    }

    public function sign(int $jobId)
    {
        $payload = $this->signatureInput[$jobId] ?? '';
        if (empty($payload)) {
            $this->errorMessage = 'Sign the job first.';

            return;
        }
        try {
            $res = app(JobSignAction::class)->handle(Tenancy::id(), $jobId, $payload);
            $this->errorMessage = null;
        } catch (\Exception $e) {
            $this->errorMessage = 'Could not sign job.';
        }
    }

    public function photo(int $jobId)
    {
        $payload = $this->photoInput[$jobId] ?? '';
        if (empty($payload)) {
            $this->errorMessage = 'Attach a photo first.';

            return;
        }
        try {
            $res = app(JobPhotoAction::class)->handle(Tenancy::id(), $jobId, $payload);
            $this->errorMessage = null;
        } catch (\Exception $e) {
            $this->errorMessage = 'Could not attach photo.';
        }
    }

    public function voiceNote(int $jobId)
    {
        $payload = $this->voiceInput[$jobId] ?? '';
        if (empty($payload)) {
            $this->errorMessage = 'Record a voice note first.';

            return;
        }
        try {
            $result = app(NoteVoiceAction::class)->handle(Tenancy::id(), $jobId, $payload);
            $this->errorMessage = null;
        } catch (\Exception $e) {
            $this->errorMessage = 'Could not attach voice note.';
        }
    }

    public function resolveConflict(int $conflictId, string $resolution)
    {
        try {
            $businessId = Tenancy::id();
            $conflict = DeviceSyncConflict::where('business_id', $businessId)->find($conflictId);
            if ($conflict) {
                $queue = DeviceSyncQueue::where('business_id', $businessId)->find($conflict->queue_id);
                if ($resolution === 'keep_mine' && $queue) {
                    $action = app(ReplayOfflineSyncAction::class);
                    // increment client version to surpass server version
                    $action->replayMutation(
                        $businessId,
                        $queue->client_mutation_id.'_replayed',
                        $queue->device_id,
                        $queue->action_name,
                        $queue->payload,
                        $conflict->server_version + 1,
                        $conflict->server_version
                    );
                }
                $conflict->conflict_reason = 'Resolved: '.$resolution;
                $conflict->save();
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Could not resolve conflict.';
        }
    }

    public function refreshApp()
    {
        $this->dispatch('refresh-app');
    }

    public function render()
    {
        $businessId = Tenancy::id();
        $techId = auth()->id();
        $today = Carbon::today();

        // tech's jobs — work_orders scheduled today for the tenant, joined to the tech's dispatch_assignments
        $jobs = DB::table('work_orders')
            ->leftJoin('dispatch_assignments', function ($join) use ($businessId) {
                $join->on('work_orders.id', '=', 'dispatch_assignments.job_id')
                    ->where('dispatch_assignments.business_id', '=', $businessId);
            })
            ->where('work_orders.business_id', $businessId)
            ->whereDate('work_orders.scheduled_at', $today)
            ->where('dispatch_assignments.tech_id', $techId)
            ->select('work_orders.id as job_id', 'work_orders.title', 'work_orders.scheduled_at', 'dispatch_assignments.status', 'dispatch_assignments.is_sample as da_sample')
            ->get();

        $queues = DeviceSyncQueue::where('business_id', $businessId)
            ->where('device_id', $this->deviceId)
            ->get();

        $conflicts = DeviceSyncConflict::where('business_id', $businessId)
            ->where('device_id', $this->deviceId)
            ->get();

        return view('x-171::stafffacing-app', [
            'jobs' => $jobs,
            'queues' => $queues,
            'conflicts' => $conflicts,
        ]);
    }
}
