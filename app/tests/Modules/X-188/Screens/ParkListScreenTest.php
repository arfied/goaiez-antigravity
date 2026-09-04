<?php

declare(strict_types=1);

namespace Tests\Modules\X188\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class ParkListScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-188.park-list'))->assertOk();

        Livewire::test(\App\Modules\X188\Ui\ParkList::class)->assertOk();
    }
}
