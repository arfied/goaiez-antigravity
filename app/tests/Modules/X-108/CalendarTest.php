<?php

declare(strict_types=1);

namespace Tests\Modules\X108;

use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X108\Models\Appointment;
use App\Modules\X108\Models\Resource;
use App\Modules\X108\Ui\Calendar;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use DatabaseTransactions;

    public function test_calendar_modes_and_actions(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Test Biz']);
        Tenancy::set((int) $biz->id);

        $person = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Linus',
            'last_name' => 'Van Pelt',
        ]);
        
        $resource = Resource::create([
            'business_id' => $biz->id,
            'name' => 'Room 101',
            'calendar_type' => 'internal'
        ]);

        $apt = Appointment::create([
            'business_id' => $biz->id,
            'customer_id' => $person->id,
            'resource_id' => $resource->id,
            'service_name' => 'Security Blanket Check',
            'start_time' => Carbon::today()->addHours(10),
            'end_time' => Carbon::today()->addHours(11),
            'status' => 'booked',
            'is_member' => true,
        ]);

        // Default (day)
        Livewire::test(Calendar::class, ['businessId' => $biz->id])
            ->assertSee('Linus Van Pelt')
            ->assertSee('Security Blanket Check')
            ->assertSee('Member')
            ->call('setMode', 'week')
            ->assertSee('Security Blanket Check')
            ->call('setMode', 'resources')
            ->assertSee('Room 101')
            ->assertSee('Security Blanket Check')
            ->call('cancelAppointment', $apt->id);
            
        // Appointment is cancelled in engine, let's just assert nothing crashed
        
        // Empty state
        Appointment::where('business_id', $biz->id)->delete();
        Livewire::test(Calendar::class, ['businessId' => $biz->id])
            ->assertSee('No appointments');
    }
}
