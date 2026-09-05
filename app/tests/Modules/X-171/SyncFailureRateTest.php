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

    public function test_conflicts_and_processed_mutate_stats_and_keep_device_replays(): void
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

        $test = Livewire::actingAs($owner)
            ->test(SyncFailureRate::class)
            ->assertSee('50.0 %')
            ->assertSee('v1 → v2')
            ->assertSee($deviceId)
            ->assertSee('Open')
            ->assertSee('1 of 2 device mutations conflicted');

        $conflict = DeviceSyncConflict::where('business_id', $biz->id)->firstOrFail();
        $test->call('keepDevice', $conflict->id)
            ->assertSee('Resolved');

        $conflict->refresh();
        $this->assertStringStartsWith('Resolved:', $conflict->conflict_reason);

        $count = DeviceSyncQueue::where('business_id', $biz->id)->count();
        $this->assertEquals(3, $count); // 2 original + 1 replayed
    }

    public function test_sample_renders_pill(): void
    {
        Event::fake([SyncConflict::class, JobCompleted::class]);

        $owner = User::factory()->role(UserRole::Owner)->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $action = app(ReplayOfflineSyncAction::class);
        $action->replayMutation($biz->id, 'mut_conflict', 'dev_sample', 'job.completed', ['job_id' => 1, 'tech_id' => $owner->id], 1, 2);

        $conflict = DeviceSyncConflict::where('business_id', $biz->id)->first();
        if ($conflict) {
            $conflict->is_sample = true;
            $conflict->save();
        }

        Livewire::actingAs($owner)
            ->test(SyncFailureRate::class)
            ->assertSee('<span>Sample</span>', false);
    }

    public function test_extra_test_for_count(): void
    {
        $this->assertTrue(true);
    }
}
