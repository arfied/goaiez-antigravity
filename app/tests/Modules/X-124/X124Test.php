<?php

declare(strict_types=1);

namespace Tests\Modules\X124;

use App\Modules\CSms\Events\SendRequested;
use App\Modules\X124\Actions\AssistantActOnRecommendationAction;
use App\Modules\X124\Actions\AssistantAskAction;
use App\Modules\X124\Actions\AssistantExecuteAction;
use App\Modules\X124\Actions\AssistantPreviewAction;
use App\Modules\X124\Actions\AssistantRecommendAction;
use App\Modules\X124\Domain\AssistantRecommendationActionRefused;
use App\Modules\X124\Events\AssistantActed;
use App\Modules\X124\Events\AssistantRecommended;
use App\Modules\X124\Events\AssistantRequest;
use App\Modules\X124\Models\AssistantSession;
use App\Modules\X124\Models\AssistantUnsupported;
use App\Modules\X124\Ui\ChatDockEvery;
use App\Modules\X124\Ui\PreviewCard;
use App\Modules\X124\Ui\TodaysRecommendationStrip;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
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
     *
     * ⛔ REFUSED: G1-30 (check) — a doctor rule fails any path where an internal-flagged message reaches a driver
     * (G1-30 internal_only is a BUILD PROPOSAL for X-01)
     * (G5-28 is UNRESOLVED, X-111 owns escalation target)
     * (G21-10 is a BUILD PROPOSAL for X-124)
     */
    public function test_g1_30_internal_message_has_no_send_requested_in_its_trace(): void
    {
        Event::fake([
            AssistantRequest::class,
            SendRequested::class,
        ]);

        $biz = TestCase::provisionTenant(['name' => 'Internal Msg Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $this->askAction->handle(
            businessId: $biz->id,
            sessionToken: 'internal_tok_123',
            utterance: 'Schedule estimate'
        );

        Event::assertDispatched(AssistantRequest::class);
        Event::assertNotDispatched(SendRequested::class);
    }

    public function test_constant_irreversible_actions(): void
    {
        $this->assertEqualsCanonicalizing(
            ['delete_tenant', 'refund_charge', 'bulk_delete', 'wipe_database'],
            AssistantExecuteAction::IRREVERSIBLE,
        );

        $biz = TestCase::provisionTenant(['name' => 'Constant Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        foreach (AssistantExecuteAction::IRREVERSIBLE as $actionKey) {
            $preview = $this->previewAction->handle($biz->id, $actionKey);
            $this->assertTrue($preview['is_irreversible']);

            $exec = $this->executeAction->handle($biz->id, $actionKey, [], false);
            $this->assertEquals('refused_confirmation_required', $exec['status']);
        }

        $ordinaryKey = 'send_invoice';
        $previewOrd = $this->previewAction->handle($biz->id, $ordinaryKey);
        $this->assertFalse($previewOrd['is_irreversible']);

        $execOrd = $this->executeAction->handle($biz->id, $ordinaryKey, [], false);
        $this->assertEquals('executed', $execOrd['status']);
    }

    public function test_todays_recommendation_strip_renders_active_and_emits_events(): void
    {
        Event::fake([
            AssistantRecommended::class,
            AssistantActed::class,
        ]);

        $biz = TestCase::provisionTenant(['name' => 'Strip Biz', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $session = AssistantSession::create(['business_id' => $biz->id, 'session_token' => 'sess_test', 'user_id' => null, 'context' => '[]']);
        $recommendAction = app(AssistantRecommendAction::class);
        $rec = $recommendAction->handle($biz->id, $session->id, 'Enable Two-Factor Auth', 'enable_2fa');

        Event::assertDispatched(AssistantRecommended::class);

        $component = Livewire::test(TodaysRecommendationStrip::class, ['businessId' => $biz->id])
            ->call('load')
            ->assertSee('Enable Two-Factor Auth')
            ->call('accept', $rec->id)
            ->assertHasNoErrors();

        Event::assertDispatched(AssistantActed::class, function ($event) use ($rec) {
            return $event->recommendationId === $rec->id && $event->action === 'accepted';
        });

        $this->assertEquals('accepted', $rec->refresh()->status);

        Livewire::test(TodaysRecommendationStrip::class, ['businessId' => $biz->id])
            ->call('load')
            ->assertDontSee('Enable Two-Factor Auth');
    }

    public function test_assistant_act_on_recommendation_refuses_invalid_status(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Strip Biz 2', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $session = AssistantSession::create(['business_id' => $biz->id, 'session_token' => 'sess_test2', 'user_id' => null, 'context' => '[]']);
        $recommendAction = app(AssistantRecommendAction::class);
        $rec = $recommendAction->handle($biz->id, $session->id, 'Enable Two-Factor Auth 2', 'enable_2fa');

        $this->expectException(AssistantRecommendationActionRefused::class);

        $actAction = app(AssistantActOnRecommendationAction::class);
        $actAction->handle($biz->id, $rec->id, 'invalid_status');
    }

    public function test_todays_recommendation_strip_blade_renders_the_error_panel(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Strip Biz Error', 'currency' => 'USD']);

        Livewire::test(TodaysRecommendationStrip::class, ['businessId' => $biz->id])
            ->call('load')
            ->set('errorMessage', 'Failed to load recommendations')
            ->assertSee('We could not load recommendations.')
            ->assertSee('Failed to load recommendations')
            ->assertSee('wire:click="load"', false);
    }

    public function test_preview_card_ready_reversible(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Preview Reversible', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        Livewire::test(PreviewCard::class, [
            'businessId' => $biz->id,
            'actionKey' => 'send_invoice',
            'params' => [],
        ])
            ->call('load')
            ->assertSeeHtml('data-irreversible="no"')
            ->assertSeeHtml('data-action-key="send_invoice"')
            ->assertSee('Will execute send_invoice with given parameters')
            ->assertDontSee('Warning: this action cannot be undone and needs confirmation.');
    }

    public function test_preview_card_ready_irreversible(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Preview Irreversible', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        Livewire::test(PreviewCard::class, [
            'businessId' => $biz->id,
            'actionKey' => 'delete_tenant',
            'params' => [],
        ])
            ->call('load')
            ->assertSeeHtml('data-irreversible="yes"')
            ->assertSeeHtml('data-action-key="delete_tenant"')
            ->assertSee('Will execute delete_tenant with given parameters')
            ->assertSee('Warning: this action cannot be undone and needs confirmation.');
    }

    public function test_chat_dock_renders_default_state(): void
    {
        Livewire::test(ChatDockEvery::class)
            ->assertSee('Copilot Assistant Chat Dock')
            ->assertSee('Ask me anything about your business.');
    }

    public function test_chat_dock_renders_answered_state(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Chat Dock Biz', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        Livewire::test(ChatDockEvery::class, ['businessId' => $biz->id])
            ->set('utterance', 'show invoices')
            ->call('ask')
            ->assertSee('show invoices')
            ->assertSeeHtml('data-status="answered"');
    }

    public function test_chat_dock_renders_unsupported_state(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Chat Dock Biz Unsupported', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        Livewire::test(ChatDockEvery::class, ['businessId' => $biz->id])
            ->set('utterance', 'Fly me to Mars')
            ->call('ask')
            ->assertSee('Fly me to Mars')
            ->assertSee('I can\'t do that yet')
            ->assertSeeHtml('data-status="unsupported"');

        $this->assertDatabaseHas('assistant_unsupported', [
            'business_id' => $biz->id,
            'utterance' => 'Fly me to Mars',
        ]);
    }

    public function test_chat_dock_ask_without_business_id_refuses_cleanly(): void
    {
        Livewire::test(ChatDockEvery::class)
            ->set('utterance', 'show invoices')
            ->call('ask')
            ->assertSee('show invoices')
            ->assertSeeHtml('data-status="unsupported"');
    }
}
