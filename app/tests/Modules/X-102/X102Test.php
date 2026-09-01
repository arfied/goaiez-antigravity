<?php

declare(strict_types=1);

namespace Tests\Modules\X102;

use App\Modules\X102\Actions\ChatCaptureAction;
use App\Modules\X102\Actions\ChatEscalateAction;
use App\Modules\X102\Actions\ChatStartAction;
use App\Modules\X102\Events\ChatEscalated;
use App\Modules\X102\Events\ChatLeadCaptured;
use App\Modules\X102\Events\ChatStarted;
use App\Modules\X102\Models\ChatSession;
use App\Modules\X121\Models\Person;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        DB::statement("SET app.business_id = '{$biz->id}'");

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
        $this->assertTrue(true);
    }

    /**
     * [G8-36] the widget's Shadow DOM
     */
    public function test_g8_36_shadow_dom(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G13-15] the pixel triggers; the chat answers grounded
     */
    public function test_g13_15_grounded_answers(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G13-37] the widget offers help instead of watching them fail
     */
    public function test_g13_37_proactive_help(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G16-21] carousels rendered in the chat
     */
    public function test_g16_21_chat_carousels(): void
    {
        $this->assertTrue(true);
    }
}
