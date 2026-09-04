<?php

namespace Tests\Modules\X117\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class CheckoutBlockScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-117.checkout-block'))->assertOk();

        Livewire::test(\App\Modules\X117\Ui\CheckoutBlock::class)->assertOk();
    }
}
