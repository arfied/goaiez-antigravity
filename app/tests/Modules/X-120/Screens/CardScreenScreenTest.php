<?php

namespace Tests\Modules\X120\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class CardScreenScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-120.card-screen'))->assertOk();

        Livewire::test(\App\Modules\X120\Ui\CardScreen::class)->assertOk();
    }
}
