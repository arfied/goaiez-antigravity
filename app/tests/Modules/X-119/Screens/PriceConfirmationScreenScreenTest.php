<?php

declare(strict_types=1);

namespace Tests\Modules\X119\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Tests\TestCase;

class PriceConfirmationScreenScreenTest extends TestCase
{
    public function test_the_tenant_route_redirects_to_prices_to_confirm(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-119.price-confirmation-screen'))->assertRedirect(route('x-163.confirmation-screen'));
    }

    public function test_the_admin_route_redirects_too(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-119.price-confirmation-screen.admin'))->assertRedirect(route('x-163.confirmation-screen'));
    }
}
