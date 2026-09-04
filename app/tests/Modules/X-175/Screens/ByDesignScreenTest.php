<?php

namespace Tests\Modules\X175\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ByDesignScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-175.by-design'))->assertOk();

        Livewire::test(\App\Modules\X175\Ui\ByDesign::class)->assertOk();
    }
}
