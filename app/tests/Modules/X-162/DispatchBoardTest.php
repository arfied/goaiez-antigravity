<?php

declare(strict_types=1);

namespace Tests\Modules\X162;

use App\Models\User;
use App\Modules\X162\Models\DispatchAssignment;
use App\Modules\X162\Models\EtaPrediction;
use App\Modules\X162\Ui\DispatchBoard;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DispatchBoardTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(DispatchBoard::class)->assertForbidden();
    }

    public function test_fresh_tenant_renders_the_empty_sentence(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(DispatchBoard::class)
            ->assertOk()
            ->assertSee('There are no jobs assigned for today');
    }

    public function test_seeded_assignment_with_eta_renders(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Test Job',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DispatchAssignment::create([
            'business_id' => $biz->id,
            'job_id' => $jobId,
            'tech_id' => 1,
            'status' => 'en_route',
            'en_route_at' => Carbon::now(),
            'is_sample' => true,
        ]);

        EtaPrediction::create([
            'business_id' => $biz->id,
            'job_id' => $jobId,
            'estimated_arrival_at' => Carbon::now()->addMinutes(12),
            'eta_minutes' => 12,
            'notification_sent_at' => Carbon::now(),
        ]);

        Livewire::actingAs($owner)
            ->test(DispatchBoard::class)
            ->assertSee('Test Job')
            ->assertSee('en route, 12 minutes out')
            ->assertSeeHtml('<span aria-hidden="true">▲</span>
    <span>Sample</span>');
    }

    public function test_mark_en_route_updates_status(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Test Job',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assignment = DispatchAssignment::create([
            'business_id' => $biz->id,
            'job_id' => $jobId,
            'tech_id' => 2,
            'status' => 'dispatched',
        ]);

        Livewire::actingAs($owner)
            ->test(DispatchBoard::class)
            ->call('markEnRoute', $jobId, 2);

        $this->assertDatabaseHas('dispatch_assignments', [
            'id' => $assignment->id,
            'status' => 'en_route',
        ]);

        $this->assertDatabaseHas('eta_predictions', [
            'job_id' => $jobId,
        ]);
    }

    public function test_seeded_row_reaches_the_page(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Dispatch Seam Job',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DispatchAssignment::create([
            'business_id' => $biz->id,
            'job_id' => $jobId,
            'tech_id' => 1,
            'status' => 'en_route',
            'en_route_at' => Carbon::now(),
            'is_sample' => false,
        ]);

        EtaPrediction::create([
            'business_id' => $biz->id,
            'job_id' => $jobId,
            'estimated_arrival_at' => Carbon::now()->addMinutes(718),
            'eta_minutes' => 718,
            'notification_sent_at' => Carbon::now(),
        ]);

        $pred = EtaPrediction::where('job_id', $jobId)->first();

        $this->actingAs($owner);
        $this->get(route('x-162.dispatch-board'))
            ->assertOk()
            ->assertSee("en route, {$pred->eta_minutes} minutes out");
    }
    public function test_empty_board_cannot_create_assignment(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(DispatchBoard::class)
            ->assertOk()
            ->assertSee('Dispatching a technician is not yet available from this screen.')
            ->assertDontSee('Go to Jobs');

        $this->assertSame(
            0,
            DispatchAssignment::where('business_id', $biz->id)->count(),
            'An owner on an empty board cannot cause a DispatchAssignment to exist.'
        );
    }
}
