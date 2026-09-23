<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class SegmentsScreenTest extends TestCase
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
        $response = $this->actingAs($user)->get(route('advanced.segments'));
        $response->assertOk();
        file_put_contents('test_output.html', $response->content());
        $response->assertSee('Skip to content');
        $response->assertDontSee('Internal Platform Console');
    }

    public function test_a_fresh_tenant_shows_two_zero_segments_and_no_fiction(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.segments'));
        $response->assertOk();
        file_put_contents('test_output.html', $response->content());
        $response->assertSee('Dormant customers');
        $response->assertSee('Active customers');
        $response->assertDontSee('VIP');
        $response->assertDontSee('5-Star Google Reviewers');
        $response->assertDontSee('Propensity');
    }

    public function test_the_counts_split_this_tenants_customers_by_the_dormancy_window(): void
    {
        [$userA, $bizA] = $this->createTenant(advanced: true);
        Customer::factory()->create(['first_seen_at' => now()->subDays(400)]);
        Customer::factory()->create(['first_seen_at' => now()->subDays(400)]);
        Customer::factory()->create(['first_seen_at' => now()->subDay()]);

        [$ownerB, $bizB] = $this->createTenant(advanced: true);
        Tenancy::setUser((int) $ownerB->id);
        Tenancy::set((int) $bizB->id);
        Customer::factory()->create(['first_seen_at' => now()->subDays(400)]);

        Tenancy::setUser((int) $userA->id);
        Tenancy::set((int) $bizA->id);

        $response = $this->actingAs($userA)->get(route('advanced.segments'));
        $response->assertOk();
        file_put_contents('test_output.html', $response->content());
        $response->assertSeeInOrder(['Dormant customers', '2']);
        $response->assertSeeInOrder(['Active customers', '1']);
        $response->assertSee(route('advanced.broadcasts.compose'));
    }
}
