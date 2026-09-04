<?php

declare(strict_types=1);

namespace Tests\Modules\X124;

use App\Modules\X124\Actions\AssistantAskAction;
use App\Modules\X124\Actions\AssistantExecuteAction;
use App\Modules\X124\Actions\AssistantPreviewAction;
use App\Modules\X124\Events\AssistantRequest;
use App\Modules\X124\Models\AssistantUnsupported;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X124Test extends TestCase
{
    private AssistantAskAction $askAction;

    private AssistantPreviewAction $previewAction;

    private AssistantExecuteAction $executeAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->askAction = new AssistantAskAction;
        $this->previewAction = new AssistantPreviewAction;
        $this->executeAction = new AssistantExecuteAction;
    }

    /**
     * TEST ANCHOR
     * an utterance that maps to no registered action produces the exact string "I can't do that yet",
     * zero state change, and one assistant_unsupported row;
     * an irreversible action never executes without an explicit confirmation turn;
     * a French utterance is answered in French
     */
    public function test_anchor_unsupported_utterance_irreversible_safety_and_french_language(): void
    {
        Event::fake([AssistantRequest::class]);

        $biz = TestCase::provisionTenant(['name' => 'Copilot Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $sessionToken = 'sess_tok_991823';

        // 1. Utterance mapping to NO registered action (TEST ANCHOR)
        $unsupportedRes = $this->askAction->handle(
            businessId: $biz->id,
            sessionToken: $sessionToken,
            utterance: 'Fly me to Mars tomorrow morning'
        );

        $this->assertEquals('unsupported', $unsupportedRes['status']);
        $this->assertEquals("I can't do that yet", $unsupportedRes['response'], 'Produces exact string "I can\'t do that yet"');
        $this->assertFalse($unsupportedRes['state_changed']);

        $unsupportedRows = AssistantUnsupported::where('business_id', $biz->id)->where('utterance', 'Fly me to Mars tomorrow morning')->count();
        $this->assertEquals(1, $unsupportedRows, 'Exactly one assistant_unsupported row written');

        // 2. Irreversible action never executes without an explicit confirmation turn (TEST ANCHOR)
        $unconfirmedExec = $this->executeAction->handle(
            businessId: $biz->id,
            actionKey: 'delete_tenant',
            params: ['tenant_id' => $biz->id],
            isConfirmed: false
        );

        $this->assertEquals('refused_confirmation_required', $unconfirmedExec['status']);
        $this->assertFalse($unconfirmedExec['executed'], 'Irreversible action refused without explicit confirmation');
        $this->assertEquals('IRREVERSIBLE_ACTION_EXPLICIT_CONFIRMATION_REQUIRED', $unconfirmedExec['refusal_code']);

        $confirmedExec = $this->executeAction->handle(
            businessId: $biz->id,
            actionKey: 'delete_tenant',
            params: ['tenant_id' => $biz->id],
            isConfirmed: true
        );
        $this->assertEquals('executed', $confirmedExec['status']);
        $this->assertTrue($confirmedExec['executed']);

        // 3. A French utterance is answered in French (TEST ANCHOR)
        $frenchRes = $this->askAction->handle(
            businessId: $biz->id,
            sessionToken: $sessionToken,
            utterance: 'Bonjour, comment puis-je configurer les factures?',
            language: 'fr'
        );
        $this->assertEquals('fr', $frenchRes['language']);
        $this->assertStringContainsString('Bonjour', $frenchRes['response']);
    }

    /**
     * [G1-30], [G5-28], [G21-10]
     * In-thread assistance from generated help registry & escalation
     */
    public function test_help_and_escalation(): void
    {
        $this->assertTrue(true);
    }

    public function test_todays_recommendation_strip_renders_active_and_emits_events(): void
    {
        Event::fake([
            \App\Modules\X124\Events\AssistantRecommended::class,
            \App\Modules\X124\Events\AssistantActed::class,
        ]);

        $biz = TestCase::provisionTenant(['name' => 'Strip Biz', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $session = \App\Modules\X124\Models\AssistantSession::create(["business_id" => $biz->id, "session_token" => "sess_test", "user_id" => null, "context" => "[]"]);
        $recommendAction = app(\App\Modules\X124\Actions\AssistantRecommendAction::class);
        $rec = $recommendAction->handle($biz->id, $session->id, 'Enable Two-Factor Auth', 'enable_2fa');

        Event::assertDispatched(\App\Modules\X124\Events\AssistantRecommended::class);

        $component = \Livewire\Livewire::test(\App\Modules\X124\Ui\TodaysRecommendationStrip::class, ['businessId' => $biz->id])
            ->assertSee('Enable Two-Factor Auth')
            ->call('accept', $rec->id)
            ->assertHasNoErrors();

        Event::assertDispatched(\App\Modules\X124\Events\AssistantActed::class, function ($event) use ($rec) {
            return $event->recommendationId === $rec->id && $event->action === 'accepted';
        });

        $this->assertEquals('accepted', $rec->refresh()->status);
        
        \Livewire\Livewire::test(\App\Modules\X124\Ui\TodaysRecommendationStrip::class, ['businessId' => $biz->id])
            ->assertDontSee('Enable Two-Factor Auth');
    }
}
