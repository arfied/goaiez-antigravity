<?php

declare(strict_types=1);

namespace Tests\Modules\X125\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X125\Actions\FlowPauseAction;
use App\Modules\X125\Actions\FlowRunAction;
use App\Modules\X125\Domain\Canvas;
use App\Modules\X125\Models\Flow;
use App\Modules\X125\Models\FlowRun;
use App\Modules\X125\Models\FlowVersion;
use App\Modules\X125\Ui\FlowErrorDashboard;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class FlowErrorDashboardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-125.flow-error-dashboard'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No automation has failed')
            ->assertDontSee('this screen is planned in');

        Tenancy::set($biz->id);

        $flow = Flow::create([
            'business_id' => $biz->id,
            'name' => 'Welcome drip',
            'trigger_event' => 'deal.stage_changed',
            'is_active' => true,
            'status' => 'active',
            'consecutive_errors' => 2,
            'max_error_threshold' => 2,
        ]);

        $version = FlowVersion::create([
            'business_id' => $biz->id,
            'flow_id' => $flow->id,
            'nodes' => [['type' => 'filter', 'label' => 'Stage equals Closed Won']],
            'plain_explanation' => 'Explain',
            'version_number' => 1,
        ]);

        FlowRun::create([
            'business_id' => $biz->id,
            'flow_id' => $flow->id,
            'flow_version_id' => $version->id,
            'status' => 'error',
            'error_message' => 'SMS gateway timed out',
            'is_manual_retry' => false,
            'trigger_payload' => [],
        ]);

        FlowRun::create([
            'business_id' => $biz->id,
            'flow_id' => $flow->id,
            'flow_version_id' => $version->id,
            'status' => 'success',
            'is_manual_retry' => false,
            'trigger_payload' => [],
        ]);

        app(FlowPauseAction::class)->handle((int) $biz->id, (int) $flow->id);
        $flow->update(['status' => 'paused_error']);

        Tenancy::forget();

        $this->get(route('x-125.flow-error-dashboard'))
            ->assertOk()
            ->assertSee('Welcome drip')
            ->assertSee('SMS gateway timed out')
            ->assertSee('Paused automatically')
            ->assertDontSee('No automation has failed')
            // Red B assertion
            ->assertSee('1 failed run');

        Livewire::test(FlowErrorDashboard::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-125.flow-error-dashboard.admin'))->assertOk();

        Livewire::test(FlowErrorDashboard::class)->assertOk();
    }

    public function test_can_pause_a_running_flow(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $flow = Canvas::createFlow((int) $biz->id, 'Nightly invoice chase', 'cron', []);
        $flow->update(['status' => 'active']);

        Livewire::test(FlowErrorDashboard::class)
            ->call('pauseFlow', $flow->id)
            ->assertSet('success', 'Paused. Nightly invoice chase will not run until you resume it. Nothing else is notified.');

        $this->assertDatabaseHas('flows', ['id' => $flow->id, 'status' => 'paused']);
    }

    public function test_a_paused_flow_is_listed_as_paused_by_you_not_as_an_error(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $flow = Canvas::createFlow((int) $biz->id, 'Nightly invoice chase', 'cron', []);
        $flow->update(['status' => 'paused']);

        Tenancy::forget();

        $this->get(route('x-125.flow-error-dashboard'))
            ->assertOk()
            ->assertSee('Nightly invoice chase')
            ->assertSee('you paused this')
            ->assertDontSee('stopped after repeated errors');
    }

    public function test_an_error_paused_flow_still_reads_as_an_error(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $flow = Canvas::createFlow((int) $biz->id, 'Nightly invoice chase', 'cron', []);
        $flow->update(['status' => 'active', 'consecutive_errors' => 2, 'max_error_threshold' => 2]);

        $action = app(FlowRunAction::class);
        $action->handle((int) $biz->id, $flow->id, [], false, true);

        Tenancy::forget();

        $this->get(route('x-125.flow-error-dashboard'))
            ->assertOk()
            ->assertSee('Nightly invoice chase')
            ->assertSee('stopped after repeated errors')
            ->assertDontSee('you paused this');
    }

    public function test_can_resume_a_paused_flow(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $flow = Canvas::createFlow((int) $biz->id, 'Nightly invoice chase', 'cron', []);
        $flow->update(['status' => 'paused', 'consecutive_errors' => 2]);

        Livewire::test(FlowErrorDashboard::class)
            ->call('resumeFlow', $flow->id)
            ->assertSet('success', 'Resumed. Nightly invoice chase is active again and its error count is back to zero.');

        $this->assertDatabaseHas('flows', ['id' => $flow->id, 'status' => 'active', 'consecutive_errors' => 0]);
    }

    public function test_a_paused_flow_refuses_an_automatic_run(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $flow = Canvas::createFlow((int) $biz->id, 'Nightly invoice chase', 'cron', []);
        $flow->update(['status' => 'paused']);

        $action = app(FlowRunAction::class);

        // Nothing triggers flows automatically today, so this guard is closing a latent hole.
        $result = $action->handle((int) $biz->id, $flow->id, [], false);
        $this->assertEquals('refused_paused', $result['status']);
        $this->assertEquals('FLOW_PAUSED_BY_OWNER', $result['refusal_code']);

        $resultManual = $action->handle((int) $biz->id, $flow->id, [], true);
        $this->assertNotEquals('refused_paused', $resultManual['status']);
    }

    public function test_pausing_another_tenants_flow_is_refused(): void
    {
        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);
        $this->actingAs($ownerB);
        $flowB = Canvas::createFlow((int) $bizB->id, 'Nightly invoice chase', 'cron', []);

        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);
        $this->actingAs($ownerA);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(FlowErrorDashboard::class)
            ->call('pauseFlow', $flowB->id);
    }
}
