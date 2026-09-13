<?php

declare(strict_types=1);

namespace Tests\Modules\X125\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X125\Actions\FlowPauseAction;
use App\Modules\X125\Models\Flow;
use App\Modules\X125\Models\FlowRun;
use App\Modules\X125\Models\FlowVersion;
use App\Modules\X125\Ui\FlowErrorDashboard;
use App\Support\Tenancy;
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
}
