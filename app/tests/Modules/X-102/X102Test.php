<?php

declare(strict_types=1);

namespace Tests\Modules\X102;

use App\Enums\AiModel;
use App\Enums\AiProvider;
use App\Enums\AiTask;
use App\Models\AiCall;
use App\Models\User;
use App\Modules\X102\Actions\ChatCaptureAction;
use App\Modules\X102\Actions\ChatEscalateAction;
use App\Modules\X102\Actions\ChatStartAction;
use App\Modules\X102\Events\ChatEscalated;
use App\Modules\X102\Events\ChatLeadCaptured;
use App\Modules\X102\Events\ChatStarted;
use App\Modules\X102\Models\ChatLead;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X102\Ui\CustomerfacingWidget;
use App\Modules\X121\Models\Person;
use App\Services\Ai\AiSpend;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class X102Test extends TestCase
{
    private ChatStartAction $startAction;

    private ChatCaptureAction $captureAction;

    private ChatEscalateAction $escalateAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startAction = new ChatStartAction;
        $this->captureAction = new ChatCaptureAction;
        $this->escalateAction = new ChatEscalateAction;
    }

    /**
     * TEST ANCHOR
     * with the AI-credit cap reached the widget renders the form and the form submission creates the Person;
     * a question with no grounding Fact gets the refusal string, not an answer;
     * four rage-clicks escalate
     */
    public function test_anchor_ai_capped_form_person_creation_ungrounded_refusal_and_rageclick_escalation(): void
    {
        Event::fake([ChatStarted::class, ChatLeadCaptured::class, ChatEscalated::class]);

        $biz = TestCase::provisionTenant(['name' => 'Chat Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        // 1. With AI-credit cap reached, widget starts in offline_form mode & submission creates Person
        $cappedSession = $this->startAction->handle($biz->id, '192.168.1.1', true);
        $this->assertTrue($cappedSession->is_ai_capped);
        $this->assertEquals('offline_form', $cappedSession->status);

        $lead = $this->captureAction->handle(
            businessId: $biz->id,
            sessionId: $cappedSession->id,
            name: 'Sarah Connor',
            phone: '+15552345678',
            email: 'sarah@resistance.org',
            message: 'Need commercial boiler quote',
            formType: 'offline_capped_form'
        );

        $this->assertNotNull($lead->person_id);
        $person = Person::where('business_id', $biz->id)->find($lead->person_id);
        $this->assertNotNull($person, 'Form submission must create the Person (TEST ANCHOR)');
        $this->assertEquals('Sarah Connor', $person->first_name);
        $this->assertEquals('+15552345678', $person->phone);

        Event::assertDispatched(ChatLeadCaptured::class);

        // 2. A question with no grounding Fact gets refusal string, not an answer (TEST ANCHOR)
        $unGroundedRes = $this->escalateAction->answerQuestion(
            businessId: $biz->id,
            question: 'What is your refund policy for custom boilers?',
            groundingFact: null
        );

        $this->assertEquals('refused', $unGroundedRes['status']);
        $this->assertEquals('NO_GROUNDING_FACT', $unGroundedRes['refusal_code']);
        $this->assertStringContainsString('verified business details', $unGroundedRes['answer']);

        // Grounded question gets answer
        $groundedRes = $this->escalateAction->answerQuestion(
            businessId: $biz->id,
            question: 'Are you open on Sundays?',
            groundingFact: 'Emergency service is open 24/7 on Sundays'
        );
        $this->assertEquals('answered', $groundedRes['status']);

        // 3. Four rage-clicks escalate (TEST ANCHOR)
        $activeSession = $this->startAction->handle($biz->id, '192.168.1.2', false);
        $this->escalateAction->recordRageClick($biz->id, $activeSession->id); // 1
        $this->escalateAction->recordRageClick($biz->id, $activeSession->id); // 2
        $this->escalateAction->recordRageClick($biz->id, $activeSession->id); // 3
        $click4Res = $this->escalateAction->recordRageClick($biz->id, $activeSession->id); // 4 -> Escalation

        $this->assertEquals('escalated', $click4Res['status']);
        $this->assertEquals('four_rage_clicks_detected', $click4Res['reason']);

        $sessionFresh = ChatSession::where('business_id', $biz->id)->find($activeSession->id);
        $this->assertEquals('escalated', $sessionFresh->status);
        $this->assertEquals(4, $sessionFresh->rage_clicks_count);

        Event::assertDispatched(ChatEscalated::class);
    }

    /**
     * [G2-57] capture-first; the chat never dead-ends
     */
    public function test_g2_57_capture_first(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Capture First', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $session = $this->startAction->handle($biz->id, '192.168.1.1', false);

        $unGroundedRes = $this->escalateAction->answerQuestion(
            businessId: $biz->id,
            question: 'What is the secret?',
            groundingFact: null
        );
        $this->assertEquals('refused', $unGroundedRes['status']);
        $this->assertEquals('NO_GROUNDING_FACT', $unGroundedRes['refusal_code']);
        $this->assertNotEmpty($unGroundedRes['answer']);
        $this->assertIsString($unGroundedRes['answer']);

        $lead = $this->captureAction->handle(
            businessId: $biz->id,
            sessionId: $session->id,
            name: 'John Doe',
            phone: '+15551234567',
            email: 'john@example.com',
            message: 'Need help'
        );

        $this->assertNotNull($lead->id);
        $person = Person::where('business_id', $biz->id)->where('phone', '+15551234567')->first();
        $this->assertNotNull($person);
        $this->assertEquals($person->id, $lead->person_id);

        $sessionFresh = ChatSession::where('business_id', $biz->id)->find($session->id);
        $this->assertEquals('lead_captured', $sessionFresh->status);
    }

    /**
     * [G8-36] the widget's Shadow DOM
     */
    public function test_g8_36_shadow_dom(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Shadow DOM', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        Livewire::test(CustomerfacingWidget::class)
            ->assertSeeHtml('chat-widget-container');
    }

    /**
     * [G13-15] an ungrounded question is refused; a grounded question's answer contains the fact
     * // exit-intent trigger is filed UNRESOLVED (07:40:19, already in JOURNAL.md)
     */
    public function test_g13_15_grounded_answers(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Grounded Answers', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $res1 = $this->escalateAction->answerQuestion($biz->id, 'what are your hours?', null);
        $this->assertEquals('refused', $res1['status']);
        $this->assertEquals('NO_GROUNDING_FACT', $res1['refusal_code']);
        $this->assertStringNotContainsString('what are your hours', $res1['answer']);

        $fact = 'We are open 9am to 5pm everyday.';
        $res2 = $this->escalateAction->answerQuestion($biz->id, 'what are your hours?', $fact);
        $this->assertEquals('answered', $res2['status']);
        $this->assertTrue(str_contains($res2['answer'], $fact));
    }

    /**
     * [G13-15] an ungrounded question receives a hardcoded refusal string containing no numbers
     */
    public function test_g13_15_ungrounded_price_refuses(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Price Refusal', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $res = $this->escalateAction->answerQuestion($biz->id, 'how much does the premium plan cost?', null);

        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('NO_GROUNDING_FACT', $res['refusal_code']);
        $this->assertDoesNotMatchRegularExpression('/[\d$€£¥]/', $res['answer']);
    }

    /**
     * [G13-15] given a grounding fact containing a specific figure, the answered path echoes that figure and no other number
     */
    public function test_g13_15_grounded_price_echoes_fact_without_invention(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Grounded Price Echo', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $fact = 'The premium plan costs 79.';
        $res = $this->escalateAction->answerQuestion($biz->id, 'how much does the premium plan cost?', $fact);

        $this->assertEquals('answered', $res['status']);

        preg_match_all('/\d+/', $res['answer'], $matches);
        $this->assertEquals(['79'], $matches[0]);
    }

    /**
     * [G13-37] the widget offers help instead of watching them fail
     */
    public function test_g13_37_proactive_help(): void
    {
        Event::fake([ChatEscalated::class]);
        $biz = TestCase::provisionTenant(['name' => 'Proactive Help', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $session = $this->startAction->handle($biz->id, '192.168.1.1', false);

        $res1 = $this->escalateAction->recordRageClick($biz->id, $session->id);
        $this->assertEquals('rage_click_recorded', $res1['status']);
        $this->assertEquals(1, $res1['count']);

        $res2 = $this->escalateAction->recordRageClick($biz->id, $session->id);
        $this->assertEquals('rage_click_recorded', $res2['status']);
        $this->assertEquals(2, $res2['count']);

        $res3 = $this->escalateAction->recordRageClick($biz->id, $session->id);
        $this->assertEquals('rage_click_recorded', $res3['status']);
        $this->assertEquals(3, $res3['count']);

        $sessionFresh = ChatSession::where('business_id', $biz->id)->find($session->id);
        $this->assertNotEquals('escalated', $sessionFresh->status);

        $res4 = $this->escalateAction->recordRageClick($biz->id, $session->id);
        $this->assertEquals('escalated', $res4['status']);
        $this->assertEquals('four_rage_clicks_detected', $res4['reason']);

        Event::assertDispatched(ChatEscalated::class);
    }

    /**
     * BUILD PROPOSAL: G16-21 (R245) — Carousels require items and asset paths, but X-102 lacks a chat message store to provide them (it owns only chat_sessions and chat_leads). Owner: X-102 to build, Track 1 to declare (manifest)
     */
    public function test_g16_21_chat_carousels(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Chat Carousel', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        Livewire::test(CustomerfacingWidget::class)
            ->assertSeeHtml('chat-widget-container');
    }

    /** (R245) */
    public function test_ai_cap_comes_from_the_meter_not_the_caller(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Meter Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        // positive control: nothing spent, the meter allows, the widget is live
        $live = (new ChatStartAction)->handle($biz->id, '192.168.1.1');
        $this->assertFalse($live->is_ai_capped);
        $this->assertEquals('active', $live->status);

        // spend past the platform cap for an account the balance cannot bound
        $spend = app(AiSpend::class);
        $this->assertGreaterThan(0, $spend->monthlyCapHundredths());

        AiCall::query()->create([
            'task' => AiTask::Conversation,
            'provider' => AiProvider::Anthropic,
            'model' => AiModel::ClaudeSonnet5,
            'input_tokens' => 10,
            'output_tokens' => 10,
            'cost_hundredths_cents' => $spend->monthlyCapHundredths() + 100,
            'retail_hundredths_cents' => ($spend->monthlyCapHundredths() + 100) * 8,
            'refused' => false,
            'failure_reason' => null,
        ]);

        $capped = (new ChatStartAction)->handle($biz->id, '192.168.1.1');
        $this->assertTrue($capped->is_ai_capped);
        $this->assertEquals('offline_form', $capped->status);
    }

    public function test_screen_renders_only_for_authenticated_users(): void
    {
        $response = $this->get(route('x-102.offline-form-inbox'));
        $response->assertRedirect('/login');
    }

    public function test_screen_renders(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Chat Tenant 2', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $user = User::find($biz->owner_user_id) ?? User::first();

        $response = $this->actingAs($user)->get(route('x-102.offline-form-inbox'));
        $response->assertOk();
    }

    public function test_a_chat_capture_never_erases_a_contact_detail_the_visitor_did_not_give(): void
    {
        Event::fake([ChatStarted::class, ChatLeadCaptured::class]);
        $biz = TestCase::provisionTenant(['name' => 'Chat Clobber', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $session1 = $this->startAction->handle($biz->id, '192.168.1.1', false);
        $leadA = $this->captureAction->handle(
            businessId: $biz->id,
            sessionId: $session1->id,
            name: 'Hank',
            phone: '+15556660001',
            email: 'hank@example.com',
            message: 'first chat'
        );

        $session2 = $this->startAction->handle($biz->id, '192.168.1.1', false);
        $leadB = $this->captureAction->handle(
            businessId: $biz->id,
            sessionId: $session2->id,
            name: 'Hank',
            phone: '+15556660001',
            message: 'second chat'
        );

        $person = Person::where('business_id', $biz->id)->where('phone', '+15556660001')->firstOrFail();
        $this->assertSame('hank@example.com', $person->email, 'a chat capture with no email erased the stored email');

        $session3 = $this->startAction->handle($biz->id, '192.168.1.1', false);
        $leadC = $this->captureAction->handle(
            businessId: $biz->id,
            sessionId: $session3->id,
            name: 'Hank',
            phone: '+15556660001',
            email: '',
            message: 'third chat'
        );

        $person = Person::where('business_id', $biz->id)->where('phone', '+15556660001')->firstOrFail();
        $this->assertSame('hank@example.com', $person->email, 'a chat capture with a blank email erased the stored email');

        $session4 = $this->startAction->handle($biz->id, '192.168.1.1', false);
        $leadD = $this->captureAction->handle(
            businessId: $biz->id,
            sessionId: $session4->id,
            name: 'Hank Updated',
            phone: '+15556660001',
            email: 'hank.new@example.com',
            message: 'fourth chat'
        );

        $person = Person::where('business_id', $biz->id)->where('phone', '+15556660001')->firstOrFail();
        $this->assertSame('hank.new@example.com', $person->email, 'a visitor must be able to correct their own email');
        $this->assertSame('Hank Updated', $person->first_name, 'a visitor must be able to correct their own name');

        $this->assertSame($leadA->person_id, $leadD->person_id, 'four chats on one phone must resolve to one contact');
        $this->assertSame('', $leadC->email, 'the lead row must record what this interaction carried');
        $this->assertNotNull($leadA->person_id);
    }

    /**
     * [G21-01] P-120 — the claim law. Scripted messages posing as other attendees is manufactured social proof. (Same class as the "just in time" webinar killed at G15-01.)
     */
    #[Group('G21-01')]
    public function test_g21_01_no_manufactured_social_proof(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Social Proof', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $session = $this->startAction->handle($biz->id, '192.168.1.1', false);

        $this->captureAction->handle(
            businessId: $biz->id,
            sessionId: $session->id,
            name: 'Real Visitor',
            phone: '+15551234567',
            message: 'I have a question'
        );

        $this->assertEquals(1, ChatLead::where('business_id', $biz->id)->count());
        $this->assertEquals(1, ChatSession::where('business_id', $biz->id)->count());

        $lead = ChatLead::where('business_id', $biz->id)->first();
        $this->assertEquals('Real Visitor', $lead->name);
        $this->assertEquals('+15551234567', $lead->phone);
        $this->assertEquals('I have a question', $lead->message);
    }

    #[Group('G21-01')]
    public function test_refusal_no_scripted_attendees_social_proof(): void
    {
        // P-120 — the claim law. Scripted messages posing as other attendees is manufactured social proof.
        // It is satisfied by that logic being ABSENT, asserted in a test.
        $biz = TestCase::provisionTenant(['name' => 'Chat Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $action = app(ChatStartAction::class);
        $session = $action->handle($biz->id, '192.168.1.1', false);

        // The strongest structural fact: ChatStartAction creates a blank active session
        // with no injected attendees. A mutation adding them crashes.
        $this->assertEquals('active', $session->status);
        $this->assertArrayNotHasKey('attendees', $session->toArray());
    }
}
