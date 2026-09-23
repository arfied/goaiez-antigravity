<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use App\Modules\X142\Models\WebhookSubscription;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class IntegrationsScreenTest extends TestCase
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
        Location::factory()->create(['business_id' => $business->id]);
        $response = $this->actingAs($user)->get(route('advanced.integrations'));
        $response->assertOk();
        $response->assertSee('Skip to content');
        $response->assertDontSee('Internal Platform Console');
    }

    public function test_empty_state_renders_correctly(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        Location::factory()->create(['business_id' => $business->id]);
        $response = $this->actingAs($user)->get(route('advanced.integrations'));
        $response->assertOk();
        $response->assertSee('No webhook subscriptions yet.');
        $response->assertSee('Not connected');
    }

    public function test_one_subscription_of_this_tenant_renders_and_another_tenants_does_not(): void
    {
        [$userA, $bizA] = $this->createTenant(advanced: true);

        Tenancy::setUser((int) $userA->id);
        Tenancy::set((int) $bizA->id);

        WebhookSubscription::create([
            'business_id' => $bizA->id,
            'target_url' => 'https://distinctive-7719.example/hook',
            'event_filter' => 'review.created',
            'secret' => 'secret1',
            'is_active' => true,
        ]);

        [$userB, $bizB] = $this->createTenant(advanced: true);

        Tenancy::setUser((int) $userB->id);
        Tenancy::set((int) $bizB->id);

        WebhookSubscription::create([
            'business_id' => $bizB->id,
            'target_url' => 'https://distinctive-7720.example/hook',
            'event_filter' => 'review.created',
            'secret' => 'secret2',
            'is_active' => true,
        ]);

        Tenancy::setUser((int) $userA->id);
        Tenancy::set((int) $bizA->id);

        $response = $this->actingAs($userA)->get(route('advanced.integrations'));
        $response->assertOk();
        $response->assertSee('https://distinctive-7719.example/hook');
        $response->assertDontSee('https://distinctive-7720.example/hook');
    }

    public function test_the_inbound_endpoints_are_the_served_routes(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        Location::factory()->create(['business_id' => $business->id]);
        $response = $this->actingAs($user)->get(route('advanced.integrations'));
        $response->assertOk();
        $response->assertSee('/webhooks/stripe');
        $response->assertDontSee('/webhooks/fictional-endpoint');
    }
}
