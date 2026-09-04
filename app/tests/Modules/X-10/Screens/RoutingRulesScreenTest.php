<?php

declare(strict_types=1);

namespace Tests\Modules\X10\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class RoutingRulesScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-10.routing-rules'))->assertOk();

        Livewire::test(\App\Modules\X10\Ui\RoutingRules::class)->assertOk();
    }
}
