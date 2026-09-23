<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Enums\UserRole;
use App\Livewire\Support\Accounts;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class AccountsScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_renders_support_layout(): void
    {
        $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($admin)->get(route('support.accounts'))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_owner_gets_forbidden(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->get(route('support.accounts'))
            ->assertForbidden();
    }

    public function test_renders_tenant_business_name(): void
    {
        $admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $business = $this->provisionTenant(['owner_user_id' => $owner->id, 'name' => 'Distinctive Business 7719']);

        Tenancy::forgetAll();

        Livewire::actingAs($admin)
            ->test(Accounts::class)
            ->set('reference', (string) $business->id)
            ->call('resolve')
            ->assertSee('Distinctive Business 7719');
    }
}
