<?php

declare(strict_types=1);

namespace Tests\Modules\X119\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X119\Ui\TeachingBox;
use Livewire\Livewire;
use Tests\TestCase;

class TeachingBoxScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-119.teaching-box'))->assertOk();

        Livewire::test(TeachingBox::class)->assertOk();
    }
}
