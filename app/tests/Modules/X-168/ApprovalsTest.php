<?php

declare(strict_types=1);

namespace Tests\Modules\X168;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X168\Actions\TimesheetComputeAction;
use App\Modules\X168\Models\Timesheet;
use App\Modules\X168\Ui\ApprovalsView;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ApprovalsTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(ApprovalsView::class)->assertForbidden();
    }

    public function test_empty_sentence(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(ApprovalsView::class)
            ->assertSee('Nothing waiting for approval. Weeks close on their own and land here.');
    }

    public function test_past_sheet_listed_current_not_listed(): void
    {
        $user = User::factory()->create(['name' => 'John']);
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // current week
        $now = Carbon::today()->addHours(8);
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

        // past week
        $past = Carbon::today()->subWeeks(2)->addHours(8);
        app(TimesheetComputeAction::class)->recordJobWindow(
            businessId: $biz->id,
            personId: $user->id,
            jobId: 7702,
            stateWindow: 'en_route',
            startedAt: $past,
            endedAt: $past->copy()->addMinutes(60),
            locationLat: 37.7749,
            locationLng: -122.4194
        );

        $currentStart = $now->startOfWeek()->format('Y-m-d');
        $pastStart = $past->startOfWeek()->format('Y-m-d');

        Livewire::actingAs($user)->test(ApprovalsView::class)
            ->assertSee($pastStart)
            ->assertDontSee($currentStart);
    }

    public function test_approve_action(): void
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
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'total_hours' => 1.5,
            'status' => 'open',
            'is_sample' => false,
        ]);

        Livewire::actingAs($user)->test(ApprovalsView::class)
            ->call('approve', $sheet->id);

        $this->assertDatabaseHas('timesheets', ['id' => $sheet->id, 'status' => 'approved']);
    }

    public function test_approve_all_action(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Owner;
        $user->save();
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $sheet1 = Timesheet::create([
            'business_id' => $biz->id,
            'person_id' => $user->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'total_hours' => 1.5,
            'status' => 'open',
            'is_sample' => false,
        ]);

        $sheet2 = Timesheet::create([
            'business_id' => $biz->id,
            'person_id' => $user->id,
            'period_start' => '2026-08-08',
            'period_end' => '2026-08-14',
            'total_hours' => 2.0,
            'status' => 'open',
            'is_sample' => false,
        ]);

        Livewire::actingAs($user)->test(ApprovalsView::class)
            ->call('approveAll');

        $this->assertDatabaseHas('timesheets', ['id' => $sheet1->id, 'status' => 'approved']);
        $this->assertDatabaseHas('timesheets', ['id' => $sheet2->id, 'status' => 'approved']);
    }

    public function test_seeded_timesheet_reaches_the_approvals_page(): void
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
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'total_hours' => 11.55,
            'status' => 'open',
            'is_sample' => false,
        ]);

        $this->actingAs($user)
            ->get(route('x-168.approvals'))
            ->assertOk()
            ->assertSee('11:33');
    }
}
