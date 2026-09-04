<?php

namespace Tests\Modules\X193\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class QuiethourHoldsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-193.quiethour-holds'))->assertOk();

        Livewire::test(\App\Modules\X193\Ui\QuiethourHolds::class)->assertOk();
    }
}
