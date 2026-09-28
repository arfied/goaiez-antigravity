<?php

declare(strict_types=1);

namespace Tests\Modules\X168;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X168\Actions\TimesheetComputeAction;
use App\Modules\X168\Models\Timesheet;
use App\Modules\X168\Ui\TimesheetsView;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TimesheetsTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(TimesheetsView::class)->assertForbidden();
    }

    public function test_staff_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(TimesheetsView::class)->assertForbidden();
    }

    public function test_empty_sentence(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(TimesheetsView::class)
            ->assertSee('No timesheets yet. Hours appear when a technician arrives on site.');
    }

    public function test_seeded_sheet_shows_name_hours_and_no_sample_pill(): void
    {
        $user = User::factory()->create(['name' => 'John Tech']);
        $user->role = UserRole::Owner;
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
            endedAt: $now->copy()->addMinutes(45),
            locationLat: 37.7749,
            locationLng: -122.4194
        );

        Livewire::actingAs($user)->test(TimesheetsView::class)
            ->assertSee('John Tech')
            ->assertSee('0:45')
            ->assertDontSee('<span>Sample</span>', false);
    }

    public function test_sample_sheet_shows_sample_pill(): void
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
            'period_start' => '2026-08-25',
            'period_end' => '2026-08-31',
            'total_hours' => 1.5,
            'status' => 'open',
            'is_sample' => true,
        ]);

        Livewire::actingAs($user)->test(TimesheetsView::class)
            ->assertSeeHtml('<span>Sample</span>');
    }

    public function test_toggle_shows_entries(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
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
            endedAt: $now->copy()->addMinutes(45),
            locationLat: 37.7749,
            locationLng: -122.4194
        );
        $sheet = Timesheet::first();

        Livewire::actingAs($user)->test(TimesheetsView::class)
            ->assertDontSee('en_route')
            ->call('toggle', $sheet->id)
            ->assertSee('en_route');
    }

    public function test_reopen_action(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $sheet = Timesheet::create([
            'business_id' => $biz->id,
            'person_id' => $user->id,
            'period_start' => '2026-08-25',
            'period_end' => '2026-08-31',
            'total_hours' => 1.5,
            'status' => 'approved',
            'is_sample' => false,
        ]);

        Livewire::actingAs($user)->test(TimesheetsView::class)
            ->call('reopen', $sheet->id);

        $this->assertDatabaseHas('timesheets', ['id' => $sheet->id, 'status' => 'open']);
    }

    public function test_seeded_timesheet_reaches_the_page(): void
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
            'period_start' => '2026-08-25',
            'period_end' => '2026-08-31',
            'total_hours' => 7.75,
            'status' => 'open',
            'is_sample' => false,
        ]);

        $this->actingAs($user)
            ->get(route('x-168.timesheets'))
            ->assertOk()
            ->assertSee('7:45');
    }
}
