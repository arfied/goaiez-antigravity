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
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Agent,C-Ai,C-Mail,C-Sms,X-66,X-102,X-118,X-209'])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseHas('agent_turns', ['business_id' => $biz->id, 'status' => 'answered']);
        $this->assertDatabaseHas('agent_refusals', ['business_id' => $biz->id, 'refusal_code' => 'UNDER_18']);
        $this->assertDatabaseHas('agent_instructions', ['business_id' => $biz->id, 'instruction_key' => 'custom_greeting']);
        $this->assertDatabaseHas('chat_sessions', ['business_id' => $biz->id, 'status' => 'active']);
        $this->assertDatabaseHas('chat_leads', ['business_id' => $biz->id, 'form_type' => 'offline_capped_form']);
        $this->assertDatabaseHas('chat_turns', ['business_id' => $biz->id, 'author_type' => 'visitor']);
        $this->assertDatabaseHas('ai_calls', ['business_id' => $biz->id, 'task' => 'demo·summary']);
        $this->assertDatabaseHas('mail_domains', ['business_id' => $biz->id, 'domain_name' => 'demo·mail.example.com']);
        $this->assertDatabaseHas('call_sessions', ['business_id' => $biz->id, 'call_sid' => 'demo·CA1']);
        $this->assertDatabaseHas('call_autopsies', ['business_id' => $biz->id, 'sentiment' => 'positive']);
        $this->assertDatabaseHas('sms_compositions', ['business_id' => $biz->id, 'recipient_phone' => '+15125550142']);
        $this->assertDatabaseHas('sms_compositions', ['business_id' => $biz->id, 'recipient_phone' => '+15125550188', 'status' => 'halted']);
        $this->assertDatabaseHas('suppressions', ['business_id' => $biz->id, 'reason' => 'demo·replied STOP']);
        $this->assertDatabaseHas('fixer_commands', ['business_id' => $biz->id, 'parsed_intent' => 'job.eta_updated']);
        $this->assertDatabaseHas('onboarding_steps', ['business_id' => $biz->id, 'step_name' => 'demo·industry inferred']);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Agent,C-Ai,C-Mail,C-Sms,X-66,X-102,X-118,X-209'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertDatabaseCount('agent_turns', 3);
        $this->assertDatabaseCount('agent_refusals', 2);
        $this->assertDatabaseCount('agent_instructions', 1);
        $this->assertDatabaseCount('chat_sessions', 2);
        $this->assertDatabaseCount('chat_leads', 1);
        $this->assertDatabaseCount('chat_turns', 2);
        $this->assertDatabaseCount('ai_calls', 2);
        $this->assertDatabaseCount('sms_compositions', 3);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--purge' => true, '--only' => 'C-Agent,C-Ai,C-Mail,C-Sms,X-66,X-102,X-118,X-209'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertDatabaseMissing('agent_turns', ['business_id' => $biz->id, 'status' => 'answered']);
        $this->assertDatabaseMissing('agent_refusals', ['business_id' => $biz->id, 'refusal_code' => 'UNDER_18']);
        $this->assertDatabaseMissing('agent_instructions', ['business_id' => $biz->id, 'instruction_key' => 'custom_greeting']);
        $this->assertDatabaseMissing('chat_sessions', ['business_id' => $biz->id, 'status' => 'active']);
        $this->assertDatabaseMissing('chat_leads', ['business_id' => $biz->id, 'form_type' => 'offline_capped_form']);
        $this->assertDatabaseMissing('chat_turns', ['business_id' => $biz->id, 'author_type' => 'visitor']);
        $this->assertDatabaseMissing('ai_calls', ['business_id' => $biz->id, 'task' => 'demo·summary']);
        $this->assertDatabaseMissing('mail_domains', ['business_id' => $biz->id, 'domain_name' => 'demo·mail.example.com']);
        $this->assertDatabaseMissing('call_sessions', ['business_id' => $biz->id, 'call_sid' => 'demo·CA1']);
        $this->assertDatabaseMissing('call_autopsies', ['business_id' => $biz->id, 'sentiment' => 'positive']);
        $this->assertDatabaseMissing('sms_compositions', ['business_id' => $biz->id, 'recipient_phone' => '+15125550142']);
        $this->assertDatabaseMissing('sms_compositions', ['business_id' => $biz->id, 'recipient_phone' => '+15125550188']);
        $this->assertDatabaseMissing('suppressions', ['business_id' => $biz->id, 'reason' => 'demo·replied STOP']);
        $this->assertDatabaseMissing('fixer_commands', ['business_id' => $biz->id, 'parsed_intent' => 'job.eta_updated']);
        $this->assertDatabaseMissing('onboarding_steps', ['business_id' => $biz->id, 'step_name' => 'demo·industry inferred']);
    }

    public function test_the_sixty_screens_show_the_demo_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', ['email' => $owner->email, '--only' => 'C-Agent,C-Ai,C-Mail,C-Sms,X-66,X-102,X-118,X-209'])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->actingAs($owner);

        $this->get(route('c-agent.groundcheck-screen'))->assertOk()->assertSee('1 answered · 1 refused · 1 handed off');
        $this->get(route('c-agent.thread'))->assertOk()->assertSee('demo·Hi, I have a question.');
        $this->get(route('c-agent.refusalcode-distribution-per'))->assertOk()->assertSee('demo·User requested age restricted products.');
        $this->get(route('c-agent.teaching-box'))->assertOk()->assertSee('demo·Always greet the user with a smile.');

        $this->get(route('c-ai.model-board'))->assertOk()->assertSee('default_primary');

        $this->get(route('c-mail.dns-card'))->assertOk()->assertSee('demo·mail.example.com');
        $this->get(route('c-mail.warmup-calendars-per'))->assertOk()->assertSee('Day 3 (120/200)');

        $this->get(route('x-102.customerfacing-widget'))->assertOk()->assertSee('2 sessions · 1 active');
        $this->get(route('x-102.rageclick-rate'))->assertOk()->assertSee('demo·sess_rage_'.$biz->id.': 3 rage clicks');
        $this->get(route('x-102.offline-form-inbox'))->assertOk()->assertSee('demo·John Doe');
        $this->get(route('x-102.thread'))->assertOk()->assertSee('demo·Hello, I need some help.');
        $this->get(route('x-66.calls'))->assertOk()->assertSee('+15125550142')->assertSee('+15125550177')->assertSee('Transcript')->assertSee('Voicemail');
        $this->get(route('x-66.latency-p50p95-per'))->assertOk()->assertSee('1450ms');
        $this->get(route('x-66.livecoaching-whisper-panel'))->assertOk()->assertSee('demo·Good rapport');

        $this->get(route('c-sms.donottext-list'))->assertOk()->assertSee('+15125550199')->assertSee('demo·replied STOP');
        $this->get(route('c-sms.pernumber-complaint-monitoring'))->assertOk()->assertSee('+15125550142')->assertSee('1 sent · 0 halted')->assertSee('+15125550188')->assertSee('0 sent · 1 halted');
        $this->get(route('c-sms.composer-segment-warning'))->assertOk()->assertSee('demo·Spring tune-up special')->assertSee('bills as 2 segments');
        $this->get(route('c-sms.thread'))->assertOk()->assertSee('demo·Your technician is on the way');

        $this->get(route('x-209.private-inbox'))->assertOk()->assertSee('demo·running 20 late to the Maple St job');
        $this->get(route('x-209.ladders-own-state'))->assertOk()->assertSee('demo·eta_update');
        $this->get(route('x-118.groundcheck'))->assertOk()->assertSee('demo·industry inferred');
    }
}
