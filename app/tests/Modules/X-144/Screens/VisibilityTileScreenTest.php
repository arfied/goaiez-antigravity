<?php

declare(strict_types=1);

namespace Tests\Modules\X144\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X144\Ui\VisibilityTile;
use Livewire\Livewire;
use Tests\TestCase;

class VisibilityTileScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-144.visibility-tile'))->assertOk();

        Livewire::test(VisibilityTile::class)->assertOk();
    }
}
