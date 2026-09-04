<?php

namespace Tests\Modules\X188\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class YourNumberCardScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-188.your-number-card'))->assertOk();

        Livewire::test(\App\Modules\X188\Ui\YourNumberCard::class)->assertOk();
    }
}
