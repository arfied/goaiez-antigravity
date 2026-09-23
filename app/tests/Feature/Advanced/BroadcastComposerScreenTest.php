<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class BroadcastComposerScreenTest extends TestCase
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
        $response = $this->actingAs($user)->get(route('advanced.broadcasts.compose'));
        $response->assertOk();
        $response->assertSee('Skip to content');
    }

    public function test_the_composers_default_state(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.broadcasts.compose'));
        $response->assertSee('Campaign Title (Internal Reference)');
    }

    public function test_a_staff_user_gets_the_status_measured(): void
    {
        [$owner, $business] = $this->createTenant(advanced: true);
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $response = $this->actingAs($staff)->get(route('advanced.broadcasts.compose'));
        $response->assertStatus(302);
    }
}
