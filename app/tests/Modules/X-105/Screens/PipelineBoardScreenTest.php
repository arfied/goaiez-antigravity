<?php

declare(strict_types=1);

namespace Tests\Modules\X105\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X105\Ui\PipelineBoard;
use Livewire\Livewire;
use Tests\TestCase;

class PipelineBoardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-105.pipeline-board'))
            ->assertOk()
            ->assertSeeText('Cold Outreach Pipeline Board')
            ->assertSeeText('No ladders found');

        Livewire::test(PipelineBoard::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-105.pipeline-board.admin'))->assertOk();

        Livewire::test(PipelineBoard::class)->assertOk();
    }

    public function test_can_start_outreach(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(PipelineBoard::class)
            ->set('prospectName', 'Test Prospect')
            ->set('prospectEmail', 'test@prospect.com')
            ->call('startOutreach')
            ->assertSet('error', null)
            ->assertSet('success', 'Started ladder and its four rungs. This feeds the pipeline board; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas('people', [
            'business_id' => $biz->id,
            'first_name' => 'Test Prospect',
            'email' => 'test@prospect.com',
        ]);

        $this->assertDatabaseHas('outreach_ladders', [
            'business_id' => $biz->id,
        ]);

        $this->get(route('x-105.pipeline-board'))
            ->assertOk()
            // OutreachLadder has no `person` relation, so it renders the fallback `Person #<id>`
            ->assertSee('Person #')
            ->assertDontSee('No ladders found');
    }

    public function test_refuses_empty_input(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(PipelineBoard::class)
            ->set('prospectName', '')
            ->set('prospectEmail', '')
            ->call('startOutreach')
            ->assertSet('error', 'Name and email are required.');

        $this->assertDatabaseMissing('outreach_ladders', [
            'business_id' => $biz->id,
        ]);
    }

    public function test_aborts_in_sample_mode(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(PipelineBoard::class)
            ->call('toggleSample')
            ->set('prospectName', 'Test Prospect')
            ->set('prospectEmail', 'test@prospect.com')
            ->call('startOutreach')
            ->assertSet('error', null)
            ->assertSet('success', null);

        $this->assertDatabaseMissing('outreach_ladders', [
            'business_id' => $biz->id,
        ]);
    }
}
