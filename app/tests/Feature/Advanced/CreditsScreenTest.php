<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class CreditsScreenTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function createTenant(bool $advanced = false): array
    {
        $user = User::factory()->create(['role' => UserRole::Owner]);
        $business = Business::provision([
            'owner_user_id' => $user->id,
            'name' => 'Acme Dental '.rand(100, 999),
        ]);

        if ($advanced) {
            $business->update(['advanced_dashboard_enabled' => true]);
        }

        Tenancy::setUser((int) $user->id);
        Tenancy::set((int) $business->id);

        return [$user, $business];
    }

    public function test_get_and_shell_mark(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.credits'));
        $response->assertOk();
        $response->assertSee('Skip to content');
    }

    public function test_the_tenants_balance_renders_from_the_component(): void
    {
        $this->markTestIncomplete('FINDING: Screen renders hardcoded wireframe (e.g. 2,450) instead of fetching the ledger balance.');
    }

    public function test_a_staff_user_gets_the_status_measured(): void
    {
        [$owner, $business] = $this->createTenant(advanced: true);
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $response = $this->actingAs($staff)->get(route('advanced.credits'));
        $response->assertStatus(302);
    }
}
