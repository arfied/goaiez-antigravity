<?php

declare(strict_types=1);

use App\Modules\X108\Actions\AppointmentListAction;
use App\Modules\X108\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

test('it returns upcoming appointments capped and ordered', function (): void {
    $biz = TestCase::provisionTenant(['name' => 'My Biz']);
    DB::statement("SET app.business_id = '{$biz->id}'");

    // Past appointment for our business
    Appointment::create([
        'business_id' => $biz->id,
        'service_name' => 'Past Service',
        'start_time' => Carbon::now()->subDays(2),
        'end_time' => Carbon::now()->subDays(2)->addHours(1),
        'status' => 'booked',
    ]);

    // Future appointments for our business (unordered here)
    Appointment::create([
        'business_id' => $biz->id,
        'service_name' => 'Future 2',
        'start_time' => Carbon::now()->addDays(2),
        'end_time' => Carbon::now()->addDays(2)->addHours(1),
        'status' => 'booked',
    ]);
    Appointment::create([
        'business_id' => $biz->id,
        'service_name' => 'Future 1',
        'start_time' => Carbon::now()->addDays(1),
        'end_time' => Carbon::now()->addDays(1)->addHours(1),
        'status' => 'booked',
    ]);
    Appointment::create([
        'business_id' => $biz->id,
        'service_name' => 'Future 3',
        'start_time' => Carbon::now()->addDays(3),
        'end_time' => Carbon::now()->addDays(3)->addHours(1),
        'status' => 'booked',
    ]);

    // Other business appointment
    $otherBiz = TestCase::provisionTenant(['name' => 'Other Biz']);
    DB::statement("SET app.business_id = '{$otherBiz->id}'");
    Appointment::create([
        'business_id' => $otherBiz->id,
        'service_name' => 'Other Biz Service',
        'start_time' => Carbon::now()->addDays(1),
        'end_time' => Carbon::now()->addDays(1)->addHours(1),
        'status' => 'booked',
    ]);

    DB::statement("SET app.business_id = '{$biz->id}'");

    $action = app(AppointmentListAction::class);
    $result = $action->forBusiness($biz->id, 2);

    expect($result)->toHaveCount(2)
        ->and($result[0]['service_name'])->toBe('Future 1')
        ->and($result[1]['service_name'])->toBe('Future 2');
});
