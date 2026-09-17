<?php

declare(strict_types=1);

namespace App\Modules\X162\Ui;

use App\Enums\UserRole;
use App\Modules\X162\Actions\EtaQueryAction;
use App\Modules\X162\Actions\JobDispatchAction;
use App\Modules\X162\Actions\TechEnRouteAction;
use App\Modules\X162\Models\DispatchAssignment;
use App\Modules\X162\Models\EtaPrediction;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Layout;

#[Layout('components.account.layout', ['heading' => 'Dispatch board'])]
class DispatchBoard extends Component
{
    public $techIds = [];

    public $queryResults = [];

    public $errorMessage = null;

    public function mount()
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $businessId = Tenancy::id();
        abort_unless($businessId !== null && $businessId > 0, 403);
    }

    public function markEnRoute(int $jobId, int $techId)
    {
        $this->errorMessage = null;
        try {
            $businessId = Tenancy::id();
            $action = app(TechEnRouteAction::class);
            $action->markEnRoute($businessId, $jobId, $techId);
        } catch (\Exception $e) {
            $this->errorMessage = 'Could not mark as en route.';
        }
    }

    public function queryEta(int $jobId)
    {
        $this->errorMessage = null;
        try {
            $businessId = Tenancy::id();
            $action = app(EtaQueryAction::class);
            $result = $action->handle($businessId, $jobId);
            $this->queryResults[$jobId] = $result['grounded_answer'];
        } catch (\Exception $e) {
            $this->errorMessage = 'Could not query ETA.';
        }
    }

    public function reassign(int $jobId)
    {
        $this->errorMessage = null;
        try {
            $techId = (int) ($this->techIds[$jobId] ?? 0);
            if ($techId > 0) {
                $businessId = Tenancy::id();
                $action = app(JobDispatchAction::class);
                $action->handle($businessId, $jobId, $techId);
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Could not reassign job.';
        }
    }

    public function render()
    {
        $businessId = Tenancy::id();
        $today = Carbon::today();

        $assignments = DispatchAssignment::where('business_id', $businessId)
            ->where(function ($q) use ($today) {
                $q->whereDate('created_at', $today)
                    ->orWhereDate('en_route_at', $today);
            })
            ->get();

        $jobIds = $assignments->pluck('job_id')->unique()->toArray();
        $workOrders = DB::table('work_orders')->whereIn('id', $jobIds)->pluck('title', 'id');

        $predictions = EtaPrediction::where('business_id', $businessId)
            ->whereIn('job_id', $jobIds)
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('job_id');

        return view('x-162::dispatch-board', [
            'assignments' => $assignments,
            'workOrders' => $workOrders,
            'predictions' => $predictions,
        ]);
    }
}
