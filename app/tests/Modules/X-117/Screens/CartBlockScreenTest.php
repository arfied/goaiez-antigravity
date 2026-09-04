<?php

namespace Tests\Modules\X117\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class CartBlockScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-117.cart-block'))->assertOk();

        Livewire::test(\App\Modules\X117\Ui\CartBlock::class)->assertOk();
    }
}
