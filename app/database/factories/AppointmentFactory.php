<?php

namespace Database\Factories;

use App\Modules\X108\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'service_name' => 'Dental Checkup',
            'start_time' => Carbon::now()->addHours(1),
            'end_time' => Carbon::now()->addHours(2),
            'status' => 'booked',
            'is_member' => true,
        ];
    }
}
