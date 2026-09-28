<?php

declare(strict_types=1);

namespace Tests\Feature\Advanced;

use App\Enums\CallRoutingMode;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Call;
use App\Models\SupportSetting;
use App\Models\User;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class VoiceScreenTest extends TestCase
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
        $response = $this->actingAs($user)->get(route('advanced.voice'));
        $response->assertOk();
        $response->assertSee('Skip to content');
    }

    public function test_empty_state_renders_correctly(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);
        $response = $this->actingAs($user)->get(route('advanced.voice'));
        $response->assertOk();
        $response->assertSee('No calls yet.');
    }

    public function test_one_call_of_this_tenant_renders_and_another_tenants_does_not(): void
    {
        [$userA, $bizA] = $this->createTenant(advanced: true);
        $callA = Call::factory()->create(['from_e164' => '+15551234567']);

        [$userB, $bizB] = $this->createTenant(advanced: true);
        $callB = Call::factory()->create(['from_e164' => '+15559876543']);

        Tenancy::setUser((int) $userA->id);
        Tenancy::set((int) $bizA->id);

        $response = $this->actingAs($userA)->get(route('advanced.voice'));
        $response->assertOk();
        $response->assertSee('+15551234567');
        $response->assertDontSee('+15559876543');
    }

    public function test_the_forwarding_mode_renders_from_the_service(): void
    {
        [$user, $business] = $this->createTenant(advanced: true);

        SupportSetting::query()->create(['call_routing_mode' => CallRoutingMode::Proxy]);

        $response = $this->actingAs($user)->get(route('advanced.voice'));
        $response->assertOk();
        $response->assertSee(CallRoutingMode::Proxy->label());
        $response->assertSee(CallRoutingMode::Proxy->description());
    }
}
