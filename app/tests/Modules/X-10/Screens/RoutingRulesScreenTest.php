<?php

declare(strict_types=1);

namespace Tests\Modules\X10\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X10\Actions\RoutingRulesEnsureAction;
use App\Modules\X10\Enums\RoutingRuleType;
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
            ->assertOk();

        Tenancy::set($biz->id);
        app(RoutingRulesEnsureAction::class)->handle($biz->id);

        $this->get(route('x-10.routing-rules'))
            ->assertOk()
            ->assertSee(RoutingRuleType::RETURNING_CALLER->label());

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
