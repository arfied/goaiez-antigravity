<?php

declare(strict_types=1);

namespace Tests\Modules\X200\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X200\Ui\CustomerfacingNone;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerfacingNoneScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-200.customerfacing-none'))->assertOk();

        Livewire::test(CustomerfacingNone::class)->assertOk();
    }
}
