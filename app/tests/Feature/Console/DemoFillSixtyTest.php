<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Tenancy;
use Tests\TestCase;

class DemoFillSixtyTest extends TestCase
{
    protected function tearDown(): void
    {
        Tenancy::forgetAll();
        parent::tearDown();
    }

    public function test_sixty_fillers_write_marked_rows_once(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Agent,C-Ai,X-102'])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseHas('agent_turns', ['business_id' => $biz->id, 'status' => 'answered']);
        $this->assertDatabaseHas('agent_refusals', ['business_id' => $biz->id, 'refusal_code' => 'UNDER_18']);
        $this->assertDatabaseHas('agent_instructions', ['business_id' => $biz->id, 'instruction_key' => 'custom_greeting']);
        $this->assertDatabaseHas('chat_sessions', ['business_id' => $biz->id, 'status' => 'active']);
        $this->assertDatabaseHas('chat_leads', ['business_id' => $biz->id, 'form_type' => 'offline_capped_form']);
        $this->assertDatabaseHas('chat_turns', ['business_id' => $biz->id, 'author_type' => 'visitor']);
        $this->assertDatabaseHas('ai_calls', ['business_id' => $biz->id, 'task' => 'demo·summary']);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Agent,C-Ai,X-102'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertDatabaseCount('agent_turns', 3);
        $this->assertDatabaseCount('agent_refusals', 2);
        $this->assertDatabaseCount('agent_instructions', 1);
        $this->assertDatabaseCount('chat_sessions', 2);
        $this->assertDatabaseCount('chat_leads', 1);
        $this->assertDatabaseCount('chat_turns', 2);
        $this->assertDatabaseCount('ai_calls', 2);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--purge' => true, '--only' => 'C-Agent,C-Ai,X-102'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertDatabaseMissing('agent_turns', ['business_id' => $biz->id, 'status' => 'answered']);
        $this->assertDatabaseMissing('agent_refusals', ['business_id' => $biz->id, 'refusal_code' => 'UNDER_18']);
        $this->assertDatabaseMissing('agent_instructions', ['business_id' => $biz->id, 'instruction_key' => 'custom_greeting']);
        $this->assertDatabaseMissing('chat_sessions', ['business_id' => $biz->id, 'status' => 'active']);
        $this->assertDatabaseMissing('chat_leads', ['business_id' => $biz->id, 'form_type' => 'offline_capped_form']);
        $this->assertDatabaseMissing('chat_turns', ['business_id' => $biz->id, 'author_type' => 'visitor']);
        $this->assertDatabaseMissing('ai_calls', ['business_id' => $biz->id, 'task' => 'demo·summary']);
    }

    public function test_the_sixty_screens_show_the_demo_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Agent,C-Ai,X-102'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->actingAs($owner);

        $this->get(route('c-agent.groundcheck-screen'))->assertOk()->assertSee('1 answered · 1 refused · 1 handed off');
        $this->get(route('c-agent.thread'))->assertOk()->assertSee('demo·Hi, I have a question.');
        $this->get(route('c-agent.refusalcode-distribution-per'))->assertOk()->assertSee('demo·User requested age restricted products.');
        $this->get(route('c-agent.teaching-box'))->assertOk()->assertSee('demo·Always greet the user with a smile.');

        $this->get(route('c-ai.model-board'))->assertOk()->assertSee('default_primary');

        $this->get(route('x-102.customerfacing-widget'))->assertOk()->assertSee('2 sessions · 1 active');
        $this->get(route('x-102.rageclick-rate'))->assertOk()->assertSee('demo·sess_rage_'.$biz->id.': 3 rage clicks');
        $this->get(route('x-102.offline-form-inbox'))->assertOk()->assertSee('demo·John Doe');
        $this->get(route('x-102.thread'))->assertOk()->assertSee('demo·Hello, I need some help.');
    }
}
