<?php

namespace Tests\Modules\X138\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class RoiDashboardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-138.roi-dashboard'))->assertOk();

        Livewire::test(\App\Modules\X138\Ui\RoiDashboard::class)->assertOk();
    }
}
