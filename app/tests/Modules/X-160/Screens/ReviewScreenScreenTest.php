<?php

declare(strict_types=1);

namespace Tests\Modules\X160\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewScreenScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-160.review-screen'))->assertOk();

        Livewire::test(\App\Modules\X160\Ui\ReviewScreen::class)->assertOk();
    }
}
