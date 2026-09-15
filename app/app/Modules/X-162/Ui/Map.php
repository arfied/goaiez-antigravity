<?php

declare(strict_types=1);

namespace App\Modules\X162\Ui;

use App\Enums\UserRole;
use App\Modules\X162\Models\DispatchAssignment;
use App\Modules\X162\Models\Route;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Route map'])]
class Map extends Component
{
    #[Locked]
    public int $businessId;

    public function mount()
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager), 403);
        $this->businessId = Tenancy::id();
    }

    public function render()
    {
        $routes = Route::where('business_id', $this->businessId)->orderBy('tech_id')->get();

        $jobIds = [];
        foreach ($routes as $route) {
            $order = $route->stop_order ?? [];
            foreach ($order as $jobId) {
                $jobIds[] = $jobId;
            }
        }
        $jobIds = array_unique($jobIds);

        $titles = DB::table('work_orders')->whereIn('id', $jobIds)->pluck('title', 'id');

        $assignments = DispatchAssignment::where('business_id', $this->businessId)
            ->whereIn('job_id', $jobIds)
            ->get();

        $assignmentMap = [];
        foreach ($assignments as $a) {
            $assignmentMap[$a->job_id.'_'.$a->tech_id] = $a;
        }

        $stops = [];
        $distance = [];
        $pillMap = [
            'completed' => ['ok', 'Completed'],
            'en_route' => ['attention', 'En route'],
            'on_site' => ['ok', 'On site'],
            'dispatched' => ['unknown', 'Dispatched'],
            'unassigned' => ['unknown', 'Unassigned'],
        ];

        foreach ($routes as $route) {
            $distance[$route->id] = number_format((float) $route->total_distance_km, 1, '.', '').' km';

            $routeStops = [];
            $order = $route->stop_order ?? [];

            foreach ($order as $jobId) {
                $aKey = $jobId.'_'.$route->tech_id;
                $assignment = $assignmentMap[$aKey] ?? null;
                $status = $assignment ? $assignment->status : 'unassigned';
                $isSample = $assignment ? (bool) $assignment->is_sample : false;

                $title = $titles->has($jobId) ? $titles->get($jobId) : "Job #{$jobId}";

                $routeStops[] = [
                    'job_id' => $jobId,
                    'title' => $title,
                    'status' => $status,
                    'is_sample' => $isSample,
                    'pill' => $pillMap[$status] ?? ['unknown', 'Unassigned'],
                ];
            }
            $stops[$route->id] = $routeStops;
        }

        $sentence = $routes->isEmpty() ? 'No routes yet. A route appears when a technician\'s stops are ordered.' : count($routes).' technician route'.(count($routes) === 1 ? '' : 's');

        return view('x-162::map', [
            'routes' => $routes,
            'stops' => $stops,
            'distance' => $distance,
            'sentence' => $sentence,
        ]);
    }
}
