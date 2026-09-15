<?php

declare(strict_types=1);

namespace Tests\Modules\X168\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X168\Actions\TimesheetComputeAction;
use App\Modules\X168\Ui\TimesheetsView;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
        DB::statement("SET app.business_id = '{$biz->id}'");

        $now = Carbon::parse('2024-01-01 08:00:00');
        app(TimesheetComputeAction::class)->recordJobWindow(
            businessId: $biz->id,
            personId: $owner->id,
            jobId: 7701,
            stateWindow: 'en_route',
            startedAt: $now,
            endedAt: $now->copy()->addMinutes(125),
            locationLat: 37.7749,
            locationLng: -122.4194
        );

        // second tenant isolation
        $biz2 = $this->provisionTenant();
        $owner2 = User::factory()->create(['role' => UserRole::Owner]);
        $tech2 = User::factory()->create(['role' => UserRole::Staff, 'name' => 'Bob Tech']);
        DB::statement("SET app.business_id = '{$biz2->id}'");
        app(TimesheetComputeAction::class)->recordJobWindow(
            businessId: $biz2->id,
            personId: $tech2->id,
            jobId: 9999,
            stateWindow: 'en_route',
            startedAt: $now,
            endedAt: $now->copy()->addMinutes(60),
            locationLat: 0,
            locationLng: 0
        );
        DB::statement("SET app.business_id = '{$biz->id}'");

        // GET asserts
        $response = $this->get(route('x-168.timesheets'));
        $response->assertOk()
            ->assertSee('Alice Tech')
            ->assertDontSee('Bob Tech')
            ->assertDontSee('No timesheets yet.');

        Livewire::test(TimesheetsView::class)->assertOk();
    }
}
