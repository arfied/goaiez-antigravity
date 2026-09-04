<?php

namespace Tests\Modules\X214\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class SurchargeDisclosureScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-214.surcharge-disclosure'))->assertOk();

        Livewire::test(\App\Modules\X214\Ui\SurchargeDisclosure::class)->assertOk();
    }
}
