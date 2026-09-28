<?php

declare(strict_types=1);

namespace Tests\Modules\X142;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X142\Actions\McpInvokeAction;
use App\Modules\X142\Actions\McpTokenAction;
use App\Modules\X142\Actions\WebhookSubscribeAction;
use App\Modules\X142\Events\McpInvoked;
use App\Modules\X142\Events\TokenIssued;
use App\Modules\X142\Events\TokenRevoked;
use App\Modules\X142\Models\McpToken;
use App\Modules\X142\Ui\ConnectYourAi;
use App\Modules\X142\Ui\McpTokenRegistry;
use App\Modules\X142\Ui\WebhooksView;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * (R245) empty state wording uses standard x-ui.empty-state pattern with no action button
 * (R245) renders event_filter column, events cast is ignored as dead
 * (R245) is_active cast to boolean added to WebhookSubscription model
 */
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
        Tenancy::set((int) $biz->id);

        // 1. Issue tenant-scoped staff MCP token (G4-02 & G4-18)
        $staffToken = $this->tokenAction->issue(
            businessId: $biz->id,
            tokenName: 'Field Tech Claude Assistant',
            roleScope: 'staff',
            permissions: ['job.create', 'job.eta_notify']
        );

        $this->assertEquals('staff', $staffToken->role_scope);
        $this->assertFalse((bool) $staffToken->is_revoked);
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
        $this->assertTrue((bool) $revoked->is_revoked);
        Event::assertDispatched(TokenRevoked::class);

        // 6. Webhook subscription
        $sub = $this->webhookAction->subscribe($biz->id, 'https://example.com/webhooks/grs', 'contact.created');
        $this->assertNotNull($sub->id);
    }

    public function test_mcp_token_permissions_read_back_as_an_array(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Perms Array Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $token = $this->tokenAction->issue(
            businessId: $biz->id,
            tokenName: 'Test Perms',
            roleScope: 'staff',
            permissions: ['job.create', 'job.eta_notify']
        );

        $this->assertSame(['job.create', 'job.eta_notify'], McpToken::findOrFail($token->id)->permissions);
    }

    /**
     * [G4-02], [G4-18]
     */
    public function test_mcp_capabilities(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Capabilities Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $token = $this->tokenAction->issue(
            businessId: $biz->id,
            tokenName: 'Test Capabilities',
            roleScope: 'staff',
            permissions: ['job.create', 'job.eta_notify']
        );

        $dbToken = McpToken::findOrFail($token->id);
        $this->assertEquals('staff', $dbToken->role_scope);
        $this->assertSame(['job.create', 'job.eta_notify'], $dbToken->permissions);

        try {
            $this->tokenAction->revoke($biz->id + 100000, $token->id);
            $this->fail('revoke accepted a token id under a business id that does not own it');
        } catch (ModelNotFoundException $e) {
            // the where('business_id') guard refused it
        }

        $this->assertFalse((bool) McpToken::findOrFail($token->id)->is_revoked);
    }

    public function test_components_render(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'MCP Component Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $user = User::find($biz->owner_user_id);
        if (! $user) {
            $user = User::factory()->create();
            $biz->update(['owner_user_id' => $user->id]);
        }
        $this->actingAs($user);

        Livewire::test(ConnectYourAi::class)->assertOk();
        Livewire::test(WebhooksView::class)->assertOk();
        Livewire::test(McpTokenRegistry::class)->assertOk();

        $this->get(route('x-142.connect-your-ai'))->assertOk();
        $this->get(route('x-142.webhooks'))->assertOk();
        $this->get(route('x-142.mcp-token-registry'))->assertOk();
    }

    public function test_guest_redirects(): void
    {
        $this->get(route('x-142.connect-your-ai'))->assertRedirect('/login');
        $this->get(route('x-142.webhooks'))->assertRedirect('/login');
        $this->get(route('x-142.mcp-token-registry'))->assertRedirect('/login');
    }

    public function test_webhook_secret_is_encrypted_at_rest(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Webhook Secret Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);

        $sub = $this->webhookAction->subscribe($biz->id, 'https://example.com/webhooks/enc', 'contact.created');

        $this->assertNotNull($sub->secret);
        $this->assertStringStartsWith('sec_', $sub->secret);

        // Fetch raw database value
        $rawSecret = DB::table('webhook_subscriptions')
            ->where('id', $sub->id)
            ->value('secret');

        $this->assertNotNull($rawSecret);
        $this->assertNotEquals($sub->secret, $rawSecret);
        $this->assertStringNotContainsString('sec_', $rawSecret); // the encrypted payload should not contain the raw prefix in plain text
    }

    public function test_mcp_token_registry_empty_state(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Empty Token Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner);

        Livewire::test(McpTokenRegistry::class)
            ->assertSee('No tokens yet.')
            ->assertSee('Tokens give external systems access to your account.');
    }

    public function test_mcp_token_registry_list(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'List Token Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner);

        $token = $this->tokenAction->issue(
            businessId: $biz->id,
            tokenName: 'Test List Token',
            roleScope: 'staff',
            permissions: ['job.create']
        );

        Livewire::test(McpTokenRegistry::class)
            ->assertSeeHtml('data-revoked="no"')
            ->assertSee($token->token_name)
            ->assertSee($token->role_scope)
            ->assertDontSee($token->token_hash);
    }

    public function test_mcp_token_registry_revoked(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Revoked Token Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner);

        $token = $this->tokenAction->issue(
            businessId: $biz->id,
            tokenName: 'Test Revoked Token',
            roleScope: 'staff',
            permissions: ['job.create']
        );
        $this->tokenAction->revoke($biz->id, $token->id);

        Livewire::test(McpTokenRegistry::class)
            ->assertSeeHtml('data-revoked="yes"')
            ->assertSee($token->token_name);
    }

    public function test_webhooks_empty_state(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Empty Webhooks Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner);

        Livewire::test(WebhooksView::class)
            ->assertSee('No webhooks yet.')
            ->assertSee('Webhooks push events to your system.');
    }

    public function test_webhooks_list(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'List Webhooks Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner);

        $sub = $this->webhookAction->subscribe($biz->id, 'https://example.com/webhooks/list', 'contact.created');

        Livewire::test(WebhooksView::class)
            ->assertSeeHtml('data-active="yes"')
            ->assertSee($sub->target_url)
            ->assertDontSee($sub->secret);
    }

    public function test_connect_your_ai_empty_when_all_tokens_are_revoked(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Empty Connect AI Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner);

        $token = $this->tokenAction->issue(
            businessId: $biz->id,
            tokenName: 'Revoked Claude Desktop',
            roleScope: 'staff',
            permissions: ['job.create']
        );
        $this->tokenAction->revoke($biz->id, $token->id);

        Livewire::test(ConnectYourAi::class)
            ->assertSee('No connections yet.')
            ->assertSee('Connect an AI to get started.')
            ->assertDontSee($token->token_name);
    }

    public function test_connect_your_ai_connected(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Connected AI Tenant', 'currency' => 'USD']);
        Tenancy::set((int) $biz->id);
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner);

        $token = $this->tokenAction->issue(
            businessId: $biz->id,
            tokenName: 'Active Claude Desktop',
            roleScope: 'staff',
            permissions: ['job.create']
        );

        Livewire::test(ConnectYourAi::class)
            ->assertSeeHtml('data-connected="yes"')
            ->assertSee($token->token_name)
            ->assertSee($token->role_scope)
            ->assertDontSee($token->token_hash);
    }
}
