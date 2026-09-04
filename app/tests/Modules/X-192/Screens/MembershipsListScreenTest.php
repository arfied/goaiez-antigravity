<?php

namespace Tests\Modules\X192\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class MembershipsListScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-192.memberships-list'))->assertOk();

        Livewire::test(\App\Modules\X192\Ui\MembershipsList::class)->assertOk();
    }
}
