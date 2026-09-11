<?php

declare(strict_types=1);

namespace Tests\Modules\X171;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X171\Events\BarcodeScanned;
use App\Modules\X171\Events\TechOnSite;
use App\Modules\X171\Models\DeviceSyncConflict;
use App\Modules\X171\Ui\StafffacingApp;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class StafffacingAppTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(StafffacingApp::class)->assertForbidden();
    }

    public function test_seeded_row_reaches_the_page(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Test Tech Job',
            'scheduled_at' => Carbon::today()->setTime(14, 38),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('dispatch_assignments')->insert([
            'business_id' => $biz->id,
            'job_id' => $jobId,
            'tech_id' => $user->id,
            'status' => 'en_route',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('x-171.stafffacing-app.admin'))
            ->assertOk()
            ->assertSee('Scheduled: 2:38 PM');
    }

    public function test_staff_with_nothing_today_sees_empty_sentence(): void
    {
        $staff = User::factory()->role(UserRole::Staff)->create();

        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        Tenancy::setUser($staff->id);

        Livewire::actingAs($staff)
            ->test(StafffacingApp::class)
            ->assertOk()
            ->assertSee('You have no jobs scheduled for today');
    }

    public function test_seeded_job_renders_and_tap_dispatches_event(): void
    {
        Event::fake([TechOnSite::class]);

        $staff = User::factory()->role(UserRole::Staff)->create();

        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        Tenancy::setUser($staff->id);

        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Test Tech Job',
            'scheduled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('dispatch_assignments')->insert([
            'business_id' => $biz->id,
            'job_id' => $jobId,
            'tech_id' => $staff->id,
            'status' => 'en_route',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($staff)
            ->test(StafffacingApp::class)
            ->assertSee('Test Tech Job')
            ->call('tap', $jobId, 'on_site')
            ->assertSee('on_site');

        Event::assertDispatchedTimes(TechOnSite::class, 1);
    }

    public function test_seeded_sync_conflict_renders(): void
    {
        $staff = User::factory()->role(UserRole::Staff)->create();

        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        Tenancy::setUser($staff->id);

        $queueId = DB::table('device_sync_queue')->insertGetId([
            'business_id' => $biz->id,
            'client_mutation_id' => 'mut1',
            'device_id' => 'device_default',
            'action_name' => 'job.completed',
            'payload' => json_encode(['job_id' => 1, 'tech_id' => $staff->id]),
            'version' => 1,
            'status' => 'conflicted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DeviceSyncConflict::create([
            'business_id' => $biz->id,
            'queue_id' => $queueId,
            'device_id' => 'device_default',
            'client_version' => 1,
            'server_version' => 2,
            'conflict_reason' => 'Client stale',
            'is_sample' => true,
        ]);

        Livewire::actingAs($staff)
            ->test(StafffacingApp::class)
            ->assertSee('Sync Conflicts')
            ->assertSee('Client stale')
            ->assertSeeHtml('<span aria-hidden="true">▲</span>
    <span>Sample</span>', false);
    }

    public function test_scan_input_fires_action(): void
    {
        Event::fake([BarcodeScanned::class]);
        $staff = User::factory()->role(UserRole::Staff)->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        Tenancy::setUser($staff->id);

        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Test Tech Job',
            'scheduled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($staff)
            ->test(StafffacingApp::class)
            ->set("scanInput.{$jobId}", 'ABC-123')
            ->call('scan', $jobId);

        Event::assertDispatched(BarcodeScanned::class, function ($e) use ($jobId) {
            return $e->jobId === $jobId && $e->barcode === 'ABC-123';
        });
    }

    public function test_scan_input_empty_fires_nothing(): void
    {
        Event::fake([BarcodeScanned::class]);
        $staff = User::factory()->role(UserRole::Staff)->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        Tenancy::setUser($staff->id);

        $jobId = DB::table('work_orders')->insertGetId([
            'business_id' => $biz->id,
            'title' => 'Test Tech Job',
            'scheduled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($staff)
            ->test(StafffacingApp::class)
            ->call('scan', $jobId)
            ->assertSee('Scan a barcode first.');

        Event::assertNotDispatched(BarcodeScanned::class);
    }
}
