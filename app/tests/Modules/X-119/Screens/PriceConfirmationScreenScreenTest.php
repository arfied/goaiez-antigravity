<?php

namespace Tests\Modules\X119\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class PriceConfirmationScreenScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-119.price-confirmation-screen'))->assertOk();

        Livewire::test(\App\Modules\X119\Ui\PriceConfirmationScreen::class)->assertOk();
    }
}
