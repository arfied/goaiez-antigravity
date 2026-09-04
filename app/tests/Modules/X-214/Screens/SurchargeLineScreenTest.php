<?php

namespace Tests\Modules\X214\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class SurchargeLineScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-214.surcharge-line'))->assertOk();

        Livewire::test(\App\Modules\X214\Ui\SurchargeLine::class)->assertOk();
    }
}
