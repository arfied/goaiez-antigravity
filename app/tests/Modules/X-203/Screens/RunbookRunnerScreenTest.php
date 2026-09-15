<?php

declare(strict_types=1);

namespace Tests\Modules\X203\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X203\Models\Runbook;
use App\Modules\X203\Models\RunbookRun;
use App\Modules\X203\Ui\RunbookRunner;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RunbookRunnerScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-203.runbook-runner'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No runbooks defined.');

        Tenancy::setUser($owner->id);
        $runbook = Runbook::create([
            'business_id' => $biz->id,
            'title' => 'Distinctive Runbook 4496',
            'trigger_event' => 'distinctive_event_4496',
            'steps' => ['promote_replica', 'notify_oncall'],
        ]);
        RunbookRun::create([
            'business_id' => $biz->id,
            'runbook_id' => $runbook->id,
            'status' => 'completed',
            'executed_steps' => ['promote_replica', 'notify_oncall'],
        ]);
        Tenancy::forget();

        $this->get(route('x-203.runbook-runner'))
            ->assertOk()
            ->assertSee('Distinctive Runbook 4496')
            ->assertSee('on distinctive_event_4496 (2 steps)')
            ->assertSee('[completed]')
            ->assertDontSee('No runbooks defined.');

        Livewire::test(RunbookRunner::class)->assertOk();
    }
}
