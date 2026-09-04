<?php

namespace Tests\Modules\CAgent\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class GroundcheckScreenScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('c-agent.groundcheck-screen'))->assertOk();

        Livewire::test(\App\Modules\CAgent\Ui\GroundcheckScreen::class)->assertOk();
    }
}
