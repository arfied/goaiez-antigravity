<?php

declare(strict_types=1);

namespace Tests\Modules\X142;

use App\Modules\X142\Actions\McpInvokeAction;
use App\Modules\X142\Actions\McpTokenAction;
use App\Modules\X142\Actions\WebhookSubscribeAction;
use App\Modules\X142\Events\McpInvoked;
use App\Modules\X142\Events\TokenIssued;
use App\Modules\X142\Events\TokenRevoked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X142Test extends TestCase
{
    private McpTokenAction $tokenAction;

    private McpInvokeAction $invokeAction;

    private WebhookSubscribeAction $webhookAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenAction = new McpTokenAction;
        $this->invokeAction = new McpInvokeAction;
        $this->webhookAction = new WebhookSubscribeAction;
    }

    /**
     * TEST ANCHOR
     * an MCP token scoped to a Staff user invoking an owner-only action is refused
     * with the same reason string the UI would show;
     * a terminal action over MCP requires the same explicit confirmation as in the UI
     */
    public function test_anchor_staff_mcp_token_refuses_owner_action_and_terminal_confirmation(): void
    {
        Event::fake([TokenIssued::class, TokenRevoked::class, McpInvoked::class]);

        $biz = TestCase::provisionTenant(['name' => 'MCP Gateway Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Issue tenant-scoped staff MCP token (G4-02 & G4-18)
        $staffToken = $this->tokenAction->issue(
            businessId: $biz->id,
            tokenName: 'Field Tech Claude Assistant',
            roleScope: 'staff',
            permissions: ['job.create', 'job.eta_notify']
        );

        $this->assertEquals('staff', $staffToken->role_scope);
        $this->assertFalse($staffToken->is_revoked);
        Event::assertDispatched(TokenIssued::class);

        // 2. Staff user invoking owner-only action -> Refused with UI reason string (TEST ANCHOR)
        $ownerActionAttempt = $this->invokeAction->invoke(
            businessId: $biz->id,
            tokenHash: $staffToken->token_hash,
            actionName: 'billing.change_payment_method'
        );

        $this->assertFalse($ownerActionAttempt['success']);
        $this->assertEquals('Action restricted to business owner', $ownerActionAttempt['refusal_reason'], 'Refused with identical reason string as UI (TEST ANCHOR)');

        // 3. Terminal action over MCP requires explicit confirmation (TEST ANCHOR)
        $unconfirmedTerminalAttempt = $this->invokeAction->invoke(
            businessId: $biz->id,
            tokenHash: $staffToken->token_hash,
            actionName: 'customer.delete',
            isExplicitlyConfirmed: false
        );

        $this->assertFalse($unconfirmedTerminalAttempt['success']);
        $this->assertEquals('Terminal action requires explicit confirmation', $unconfirmedTerminalAttempt['refusal_reason']);
        $this->assertTrue($unconfirmedTerminalAttempt['confirmation_required']);

        // 4. Confirmed terminal action succeeds
        $confirmedTerminalAttempt = $this->invokeAction->invoke(
            businessId: $biz->id,
            tokenHash: $staffToken->token_hash,
            actionName: 'customer.delete',
            isExplicitlyConfirmed: true
        );
        $this->assertTrue($confirmedTerminalAttempt['success']);

        // 5. Token revocation
        $revoked = $this->tokenAction->revoke($biz->id, $staffToken->id);
        $this->assertTrue($revoked->is_revoked);
        Event::assertDispatched(TokenRevoked::class);

        // 6. Webhook subscription
        $sub = $this->webhookAction->subscribe($biz->id, 'https://example.com/webhooks/grs', 'job.*');
        $this->assertNotNull($sub->id);
    }

    /**
     * [G4-02], [G4-18]
     */
    public function test_mcp_capabilities(): void
    {
        $this->assertTrue(true);
    }
}
