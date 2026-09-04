<?php

namespace Tests\Modules\X136\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class CoolingViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-136.cooling'))->assertOk();

        Livewire::test(\App\Modules\X136\Ui\CoolingView::class)->assertOk();
    }
}
