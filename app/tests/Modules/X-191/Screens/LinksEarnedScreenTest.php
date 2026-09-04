<?php

namespace Tests\Modules\X191\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class LinksEarnedScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-191.links-earned'))->assertOk();

        Livewire::test(\App\Modules\X191\Ui\LinksEarned::class)->assertOk();
    }
}
