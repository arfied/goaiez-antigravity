<?php

declare(strict_types=1);

namespace Tests\Modules\X150\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

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
