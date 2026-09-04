<?php

namespace Tests\Modules\X82\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class RateRegistryViewScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-82.rate-registry'))->assertOk();

        Livewire::test(\App\Modules\X82\Ui\RateRegistryView::class)->assertOk();
    }
}
