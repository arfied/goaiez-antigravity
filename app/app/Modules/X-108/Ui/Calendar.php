<?php

declare(strict_types=1);

namespace App\Modules\X108\Ui;

use App\Modules\X108\Actions\AppointmentCancelAction;
use App\Modules\X108\Models\Appointment;
use App\Modules\X108\Models\Resource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

#[Layout('components.account.layout', ['heading' => 'Your Appointments'])]
class Calendar extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $mode = 'day';

    public string $date = '';

    public bool $failed = false;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
        $this->date = Carbon::today()->toDateString();
    }

    public function setMode(string $mode)
    {
        $this->mode = $mode;
    }

    public function cancelAppointment(int $id, AppointmentCancelAction $action)
    {
        $action->handle($this->businessId, $id);
    }

    public function render()
    {
        $appointments = collect();
        $resources = collect();

        try {
            $this->failed = false;

            if ($this->businessId > 0) {
                $query = Appointment::query()
                    ->where('appointments.business_id', $this->businessId)
                    ->leftJoin('people', 'appointments.customer_id', '=', 'people.id')
                    ->select('appointments.*', 'people.first_name', 'people.last_name');

                $selectedDate = $this->date ? Carbon::parse($this->date) : Carbon::today();

                if ($this->mode === 'day') {
                    $appointments = (clone $query)
                        ->whereDate('start_time', $selectedDate)
                        ->orderBy('start_time')
                        ->get();
                } elseif ($this->mode === 'week') {
                    $start = $selectedDate->copy()->startOfWeek();
                    $end = $selectedDate->copy()->endOfWeek();

                    $appointments = (clone $query)
                        ->whereBetween('start_time', [$start, $end])
                        ->orderBy('start_time')
                        ->get()
                        ->groupBy(fn ($apt) => Carbon::parse($apt->start_time)->format('Y-m-d'));
                } elseif ($this->mode === 'resources') {
                    $appointments = (clone $query)
                        ->whereDate('start_time', $selectedDate)
                        ->orderBy('start_time')
                        ->get()
                        ->groupBy('resource_id');

                    $resources = Resource::where('business_id', $this->businessId)->get()->keyBy('id');
                }
            }
        } catch (Throwable $e) {
            $this->failed = true;
        }

        return view('x-108::calendar', [
            'appointments' => $appointments,
            'resources' => $resources,
        ]);
    }
}
