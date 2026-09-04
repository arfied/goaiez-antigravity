<?php

namespace Tests\Modules\X210\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class PromotionBuilderScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-210.promotion-builder'))->assertOk();

        Livewire::test(\App\Modules\X210\Ui\PromotionBuilder::class)->assertOk();
    }
}
