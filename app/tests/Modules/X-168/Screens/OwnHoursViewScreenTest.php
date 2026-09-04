<?php

namespace Tests\Modules\X168\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class OwnHoursViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-168.own-hours'))->assertOk();

        Livewire::test(\App\Modules\X168\Ui\OwnHoursView::class)->assertOk();
    }
}
