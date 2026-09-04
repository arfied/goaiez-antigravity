<?php

namespace Tests\Modules\X207\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class PerplatformDeliveryHealthScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-207.perplatform-delivery-health'))->assertOk();

        Livewire::test(\App\Modules\X207\Ui\PerplatformDeliveryHealth::class)->assertOk();
    }
}
