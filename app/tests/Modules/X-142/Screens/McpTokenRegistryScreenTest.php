<?php

declare(strict_types=1);

namespace Tests\Modules\X142\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X142\Models\McpToken;
use App\Modules\X142\Ui\McpTokenRegistry;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class McpTokenRegistryScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-142.mcp-token-registry'))->assertOk();

        Livewire::test(McpTokenRegistry::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-142.mcp-token-registry.admin'))->assertOk();

        Livewire::test(McpTokenRegistry::class)->assertOk();
    }

    public function test_can_issue_token(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(McpTokenRegistry::class)
            ->set('tokenName', 'Test Token Name 123')
            ->set('roleScope', 'writer')
            ->call('issueToken')
            ->assertSet('success', 'Registry entry for token Test Token Name 123 was recorded (granting read). The token value is not shown and cannot be used yet.');

        $this->assertDatabaseHas('mcp_tokens', [
            'token_name' => 'Test Token Name 123',
            'is_revoked' => false,
        ]);

        Tenancy::forget();

        $this->actingAs($owner);
        $this->get(route('x-142.mcp-token-registry'))
            ->assertOk()
            ->assertSee('Test Token Name 123');
    }

    public function test_can_issue_and_revoke_token(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $token = McpToken::create([
            'business_id' => $biz->id,
            'token_name' => 'To Revoke',
            'role_scope' => 'reader',
            'permissions' => ['read'],
            'token_hash' => 'hash',
            'is_revoked' => false,
        ]);

        Livewire::test(McpTokenRegistry::class)
            ->set('revokeTokenId', $token->id)
            ->call('revokeToken')
            ->assertSet('success', 'Token To Revoke has been revoked and can no longer be used.');

        $this->assertDatabaseHas('mcp_tokens', [
            'id' => $token->id,
            'is_revoked' => true,
        ]);

        Tenancy::forget();

        $this->actingAs($owner);
        $this->get(route('x-142.mcp-token-registry'))
            ->assertOk()
            ->assertSee('data-revoked="yes"', false);
    }

    public function test_picker_excludes_revoked_tokens(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $active = McpToken::create([
            'business_id' => $biz->id,
            'token_name' => 'Active Token',
            'role_scope' => 'reader',
            'permissions' => ['read'],
            'token_hash' => 'hash1',
            'is_revoked' => false,
        ]);

        $revoked = McpToken::create([
            'business_id' => $biz->id,
            'token_name' => 'Revoked Token',
            'role_scope' => 'reader',
            'permissions' => ['read'],
            'token_hash' => 'hash2',
            'is_revoked' => true,
        ]);

        Livewire::test(McpTokenRegistry::class)
            ->assertSeeHtml('<option value="'.$active->id.'">Active Token</option>')
            ->assertDontSeeHtml('<option value="'.$revoked->id.'">Revoked Token</option>');
    }

    public function test_refuses_empty_token_name(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(McpTokenRegistry::class)
            ->set('tokenName', '   ')
            ->set('roleScope', 'writer')
            ->call('issueToken')
            ->assertSet('error', 'A token name is required.');

        $this->assertDatabaseMissing('mcp_tokens', [
            'role_scope' => 'writer',
        ]);
    }

    public function test_refuses_nothing_selected_on_revoke(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(McpTokenRegistry::class)
            ->set('revokeTokenId', 0)
            ->call('revokeToken')
            ->assertSet('error', 'Please select a token to revoke.');
    }

    public function test_the_registry_lists_only_this_tenants_tokens(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Livewire::test(McpTokenRegistry::class)
            ->set('tokenName', 'Distinctive token 4853')
            ->call('issueToken');

        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);
        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);
        $this->actingAs($ownerB);
        Livewire::test(McpTokenRegistry::class)
            ->set('tokenName', 'Distinctive token 4854')
            ->call('issueToken');

        Tenancy::setUser($owner->id);
        Tenancy::set((int) $biz->id);
        $this->actingAs($owner);
        Livewire::test(McpTokenRegistry::class)
            ->assertSee('Distinctive token 4853')
            ->assertDontSee('Distinctive token 4854');
        Tenancy::setUser($ownerB->id);
        Tenancy::set((int) $bizB->id);
        $this->assertDatabaseHas('mcp_tokens', ['token_name' => 'Distinctive token 4854']);
    }
}
