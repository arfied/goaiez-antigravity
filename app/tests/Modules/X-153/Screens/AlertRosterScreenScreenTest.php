<?php

declare(strict_types=1);

namespace Tests\Modules\X153\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X153\Models\Alert;
use App\Modules\X153\Ui\AlertRosterScreen;
use App\Modules\X207\Jobs\SendPushToUserJob;
use App\Modules\X207\Models\DeviceToken;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AlertRosterScreenScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-153.alert-roster-screen'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No alerts recorded.');

        Livewire::test(AlertRosterScreen::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-153.alert-roster-screen.admin'))->assertOk();

        Livewire::test(AlertRosterScreen::class)->assertOk();
    }

    /** Prove it shows the tenant's alerts on real route */
    public function test_shows_tenant_alerts(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Alert::create([
            'business_id' => $biz->id,
            'alert_class' => 'urgent',
            'title' => 'Test Roster Alert',
            'body' => 'Body text',
            'status' => 'pending',
        ]);

        $this->actingAs($owner);

        $this->get(route('x-153.alert-roster-screen'))
            ->assertOk()
            ->assertSee('Test Roster Alert');

        Livewire::test(AlertRosterScreen::class)
            ->assertOk()
            ->assertSee('Test Roster Alert');
    }

    public function test_owner_broadcasts_and_notifies_teammates(): void
    {
        Queue::fake();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $user1 = User::factory()->create(['role' => UserRole::Staff]);
        $user2 = User::factory()->create(['role' => UserRole::Staff]);
        $otherBizUser = User::factory()->create(['role' => UserRole::Staff]);
        $otherBiz = $this->provisionTenant(['owner_user_id' => $otherBizUser->id]);

        $subscription = [
            'endpoint' => 'https://push.example.test/sub-4954',
            'keys' => [
                'p256dh' => 'p256dh',
                'auth' => 'auth',
            ],
        ];

        Tenancy::actingAs($biz->id, function () use ($biz, $user1, $user2, $owner, $subscription) {
            DeviceToken::create([
                'business_id' => $biz->id,
                'user_id' => $user1->id,
                'platform' => 'web',
                'device_token' => hash('sha256', 'sub1'),
                'subscription' => $subscription,
                'status' => 'active',
            ]);

            DeviceToken::create([
                'business_id' => $biz->id,
                'user_id' => $user2->id,
                'platform' => 'web',
                'device_token' => hash('sha256', 'sub2'),
                'subscription' => $subscription,
                'status' => 'active',
            ]);

            DeviceToken::create([
                'business_id' => $biz->id,
                'user_id' => $owner->id,
                'platform' => 'web',
                'device_token' => hash('sha256', 'sub-owner'),
                'subscription' => $subscription,
                'status' => 'active',
            ]);
        });

        // (c) A device in ANOTHER business and a retired device are not recipients.
        Tenancy::actingAs($otherBiz->id, function () use ($otherBiz, $otherBizUser, $subscription) {
            DeviceToken::create([
                'business_id' => $otherBiz->id,
                'user_id' => $otherBizUser->id,
                'platform' => 'web',
                'device_token' => hash('sha256', 'sub-other'),
                'subscription' => $subscription,
                'status' => 'active',
            ]);
        });

        $user3 = User::factory()->create(['role' => UserRole::Staff]);
        Tenancy::actingAs($biz->id, function () use ($biz, $user3, $subscription) {
            DeviceToken::create([
                'business_id' => $biz->id,
                'user_id' => $user3->id,
                'platform' => 'web',
                'device_token' => hash('sha256', 'sub-retired'),
                'subscription' => $subscription,
                'status' => 'retired',
            ]);
        });
        $this->actingAs($owner);

        \App\Support\Tenancy::actingAs($biz->id, function() {
            Livewire::test(AlertRosterScreen::class)
                ->set('title', 'Test Alert')
                ->set('body', 'Test Body')
                ->call('broadcastAlert')
                ->assertSet('success', function ($val) {
                    return str_contains($val, 'Sent to 2 teammates');
                });
        });

        Queue::assertPushed(SendPushToUserJob::class, function ($job) use ($user1) {
            return $job->userId === $user1->id && $job->eventType === 'team_alert';
        });

        Queue::assertPushed(SendPushToUserJob::class, function ($job) use ($user2) {
            return $job->userId === $user2->id && $job->eventType === 'team_alert';
        });

        Queue::assertNotPushed(SendPushToUserJob::class, function ($job) use ($owner) {
            return $job->userId === $owner->id;
        });

        Queue::assertNotPushed(SendPushToUserJob::class, function ($job) use ($otherBizUser) {
            return $job->userId === $otherBizUser->id;
        });

        Queue::assertNotPushed(SendPushToUserJob::class, function ($job) use ($user3) {
            return $job->userId === $user3->id;
        });
    }

    public function test_owner_broadcasts_with_no_teammates(): void
    {
        Queue::fake();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $this->actingAs($owner);

        \App\Support\Tenancy::actingAs($biz->id, function() {
            Livewire::test(AlertRosterScreen::class)
                ->set('title', 'Test Alert')
                ->set('body', 'Test Body')
                ->call('broadcastAlert')
                ->assertSet('success', function ($val) {
                    return str_contains($val, 'Nobody else on your team has turned on alerts');
                });
        });

        Queue::assertNothingPushed();
    }

    public function test_staff_cannot_broadcast_alert(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $this->actingAs($staff);

        \App\Support\Tenancy::actingAs($biz->id, function() {
            Livewire::test(AlertRosterScreen::class)
                ->set('title', 'Test Alert')
                ->set('body', 'Test Body')
                ->call('broadcastAlert')
                ->assertForbidden();
        });

        $this->assertDatabaseEmpty('alerts');
    }

    public function test_push_sw_js_contains_team_alert(): void
    {
        $content = file_get_contents(public_path('push-sw.js'));
        $this->assertStringContainsString('team_alert', $content);
    }
}
