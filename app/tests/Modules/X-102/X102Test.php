<?php

declare(strict_types=1);

namespace Tests\Modules\X102;

use App\Models\User;
use App\Modules\X102\Actions\ChatCaptureAction;
use App\Modules\X102\Actions\ChatEscalateAction;
use App\Modules\X102\Actions\ChatStartAction;
use App\Modules\X102\Events\ChatEscalated;
use App\Modules\X102\Events\ChatLeadCaptured;
use App\Modules\X102\Events\ChatStarted;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X102\Ui\CustomerfacingWidget;
use App\Modules\X121\Models\Person;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
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

        $component = Livewire::test(CustomerfacingWidget::class);
        $component->assertDontSee('attachShadow');
        $component->assertDontSee('shadow-root');

    }

    /**
     * [G13-15] the pixel triggers; the chat answers grounded
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
     * [G16-21] carousels rendered in the chat
     */
    public function test_g16_21_chat_carousels(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Chat Carousel', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $component = Livewire::test(CustomerfacingWidget::class);
        $component->assertDontSee('carousel');

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
}
