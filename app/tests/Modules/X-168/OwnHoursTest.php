<?php

declare(strict_types=1);

namespace Tests\Modules\X168;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X168\Actions\TimesheetComputeAction;
use App\Modules\X168\Models\Timesheet;
use App\Modules\X168\Ui\OwnHoursView;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class OwnHoursTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(OwnHoursView::class)->assertForbidden();
    }

    public function test_empty_sentence(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(OwnHoursView::class)
            ->assertSee('No hours yet. Your hours start when you arrive on site at a job.');
    }

    public function test_shows_own_sheets_only(): void
    {
        $user1 = User::factory()->create(['name' => 'Me']);
        $user1->role = UserRole::Staff;
        $user1->save();
        $user2 = User::factory()->create(['name' => 'Other']);
        $user2->role = UserRole::Staff;
        $user2->save();

        $biz = TestCase::provisionTenant(['owner_user_id' => $user1->id]);
        Tenancy::setUser($user1->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $now = Carbon::parse('2026-08-25 08:00:00');
        app(TimesheetComputeAction::class)->recordJobWindow(
            businessId: $biz->id,
            personId: $user1->id,
            jobId: 7701,
            stateWindow: 'en_route',
            startedAt: $now,
            endedAt: $now->copy()->addMinutes(45), // 0:45
            locationLat: 37.7749,
            locationLng: -122.4194
        );
        app(TimesheetComputeAction::class)->recordJobWindow(
            businessId: $biz->id,
            personId: $user2->id,
            jobId: 7702,
            stateWindow: 'on_site',
            startedAt: $now,
            endedAt: $now->copy()->addMinutes(120), // 2:00
            locationLat: 37.7749,
            locationLng: -122.4194
        );

        Livewire::actingAs($user1)->test(OwnHoursView::class)
            ->assertSee('2026-08-24 – 2026-08-30')
            ->assertSee('0:45')
            ->assertDontSee('2:00');
    }

    public function test_entry_state_window_shown_by_default(): void
    {
        $user = User::factory()->create(['name' => 'Me']);
        $user->role = UserRole::Staff;
        $user->save();

        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $now = Carbon::parse('2026-08-25 08:00:00');
        app(TimesheetComputeAction::class)->recordJobWindow(
            businessId: $biz->id,
            personId: $user->id,
            jobId: 7701,
            stateWindow: 'en_route',
            startedAt: $now,
            endedAt: $now->copy()->addMinutes(45), // 0:45
            locationLat: 37.7749,
            locationLng: -122.4194
        );

        Livewire::actingAs($user)->test(OwnHoursView::class)
            ->assertSee('en_route');
    }

    public function test_real_get_shows_derived_magnitude(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Timesheet::create([
            'business_id' => $biz->id,
            'person_id' => $user->id,
            'period_start' => '2026-08-24',
            'period_end' => '2026-08-30',
            'total_hours' => 9.25,
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->get(route('x-168.own-hours'))
            ->assertOk()
            ->assertSee('9:15');
    }
}
