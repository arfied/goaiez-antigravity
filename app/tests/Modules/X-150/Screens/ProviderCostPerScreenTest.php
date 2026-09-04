<?php

namespace Tests\Modules\X150\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ProviderCostPerScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-150.provider-cost-per'))->assertOk();

        Livewire::test(\App\Modules\X150\Ui\ProviderCostPer::class)->assertOk();
    }
}
