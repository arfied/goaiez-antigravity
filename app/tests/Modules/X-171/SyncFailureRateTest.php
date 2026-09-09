<?php

declare(strict_types=1);

namespace Tests\Modules\X171;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X171\Actions\ReplayOfflineSyncAction;
use App\Modules\X171\Events\JobCompleted;
use App\Modules\X171\Events\SyncConflict;
use App\Modules\X171\Models\DeviceSyncConflict;
use App\Modules\X171\Models\DeviceSyncQueue;
use App\Modules\X171\Ui\SyncFailureRate;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class SyncFailureRateTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(SyncFailureRate::class)->assertForbidden();
    }

    public function test_staff_is_forbidden(): void
    {
        $staff = User::factory()->role(UserRole::Staff)->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => User::factory()->create()->id]);
        Tenancy::setUser($staff->id);

        Livewire::actingAs($staff)
            ->test(SyncFailureRate::class)
            ->assertForbidden();
    }

    public function test_owner_with_nothing_sees_empty_sentence(): void
    {
        $owner = User::factory()->role(UserRole::Owner)->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(SyncFailureRate::class)
            ->assertOk()
            ->assertSee('No device mutations yet.')
            ->assertSee('No sync conflicts. Every device mutation replayed cleanly.');
    }

    public function test_seeded_row_reaches_the_page(): void
    {
        Event::fake([SyncConflict::class, JobCompleted::class]);

        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        for ($i = 0; $i < 8; $i++) {
            DB::table('device_sync_queue')->insert([
                'business_id' => $biz->id,
                'client_mutation_id' => 'mut_'.$i,
                'device_id' => 'device_default',
                'action_name' => 'job.completed',
                'payload' => '[]',
                'version' => 1,
                'status' => $i < 5 ? 'conflicted' : 'processed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->get(route('x-171.sync-failure-rate.admin'))
            ->assertOk()
            ->assertSee('62.5 %');
    }

    public function test_conflicts_and_processed_mutate_stats(): void
    {
        Event::fake([SyncConflict::class, JobCompleted::class]);

        $owner = User::factory()->role(UserRole::Owner)->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $action = app(ReplayOfflineSyncAction::class);
        $deviceId = 'device123';

        // Conflict
        $action->replayMutation($biz->id, 'mut_conflict', $deviceId, 'job.completed', ['job_id' => 1, 'tech_id' => $owner->id], 1, 2);

        // Processed
        $action->replayMutation($biz->id, 'mut_processed', $deviceId, 'job.completed', ['job_id' => 2, 'tech_id' => $owner->id], 2, 2);

        Livewire::actingAs($owner)
            ->test(SyncFailureRate::class)
            ->assertSee('50.0 %')
            ->assertSee('v1 → v2')
            ->assertSee($deviceId)
            ->assertSee('Open')
            ->assertSee('1 of 2 device mutations conflicted');
    }

    public function test_keep_device_replays_and_resolves(): void
    {
        Event::fake([SyncConflict::class, JobCompleted::class]);

        $owner = User::factory()->role(UserRole::Owner)->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $action = app(ReplayOfflineSyncAction::class);
        $deviceId = 'device123';

        $action->replayMutation($biz->id, 'mut_conflict', $deviceId, 'job.completed', ['job_id' => 1, 'tech_id' => $owner->id], 1, 2);

        $conflict = DeviceSyncConflict::where('business_id', $biz->id)->firstOrFail();
        Livewire::actingAs($owner)
            ->test(SyncFailureRate::class)
            ->call('keepDevice', $conflict->id)
            ->assertSee('Resolved');

        $conflict->refresh();
        $this->assertStringStartsWith('Resolved:', $conflict->conflict_reason);

        $count = DeviceSyncQueue::where('business_id', $biz->id)->count();
        $this->assertEquals(2, $count);
    }

    public function test_sample_renders_pill(): void
    {
        Event::fake([SyncConflict::class, JobCompleted::class]);

        $owner = User::factory()->role(UserRole::Owner)->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $action = app(ReplayOfflineSyncAction::class);
        $action->replayMutation($biz->id, 'mut_conflict', 'dev_sample', 'job.completed', ['job_id' => 1, 'tech_id' => $owner->id], 1, 2);

        $conflict = DeviceSyncConflict::where('business_id', $biz->id)->firstOrFail();
        $conflict->is_sample = true;
        $conflict->save();

        Livewire::actingAs($owner)
            ->test(SyncFailureRate::class)
            ->assertSee('<span>Sample</span>', false);
    }
}
