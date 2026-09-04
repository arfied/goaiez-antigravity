<?php

namespace Tests\Modules\X130\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class PublicIndexPagesScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-130.public-index-pages'))->assertOk();

        Livewire::test(\App\Modules\X130\Ui\PublicIndexPages::class)->assertOk();
    }
}
