<?php

declare(strict_types=1);

namespace Tests\Modules\X10\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X10\Models\RoutingRule;
use App\Modules\X10\Ui\RoutingRules;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RoutingRulesScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-10.routing-rules'))
            ->assertOk()
            ->assertSee('No routing rules configured.');

        Tenancy::set($biz->id);
        RoutingRule::create([
            'business_id' => $biz->id,
            'name' => 'Test Routing Rule 123',
            'rule_type' => 'some_type',
            'priority' => 1,
        ]);

        $this->get(route('x-10.routing-rules'))
            ->assertOk()
            ->assertSee('Test Routing Rule 123');

        Livewire::test(RoutingRules::class, ['businessId' => $biz->id])->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-10.routing-rules.admin'))->assertOk();

        Livewire::test(RoutingRules::class, ['businessId' => $biz->id])->assertOk();
    }
}
