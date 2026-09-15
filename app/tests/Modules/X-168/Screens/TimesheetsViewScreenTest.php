<?php

declare(strict_types=1);

namespace Tests\Modules\X168\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X168\Ui\TimesheetsView;
use Livewire\Livewire;
use Tests\TestCase;

class TimesheetsViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        // empty GET → empty-state
        $this->get(route('x-168.timesheets'))
            ->assertOk()
            ->assertSee('No timesheets yet. Hours appear when a technician arrives on site.');

        // seed distinctive timesheet/entry
        $owner->name = 'Alice Tech';
        $owner->save();
        \Illuminate\Support\Facades\DB::statement("SET app.business_id = '{$biz->id}'");
        
        $now = \Carbon\Carbon::parse('2024-01-01 08:00:00');
        app(\App\Modules\X168\Actions\TimesheetComputeAction::class)->recordJobWindow(
            businessId: $biz->id,
            personId: $owner->id,
            jobId: 7701,
            stateWindow: 'en_route',
            startedAt: $now,
            endedAt: $now->copy()->addMinutes(125),
            locationLat: 37.7749,
            locationLng: -122.4194
        );

        // GET asserts
        $this->get(route('x-168.timesheets'))
            ->assertOk()
            ->assertSee('Alice Tech')
            ->assertDontSee('No timesheets yet.');

        Livewire::test(TimesheetsView::class)->assertOk();
    }
}
