<?php

namespace Tests\Modules\X01\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Modules\X-01\Ui\PersonComponent;
use App\Models\User;
use App\Enums\UserRole;

class PersonComponentScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-01.person'))->assertOk();

        Livewire::test(PersonComponent::class)->assertOk();
    }
}
