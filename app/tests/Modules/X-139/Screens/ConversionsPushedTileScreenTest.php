<?php

declare(strict_types=1);

namespace Tests\Modules\X139\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X139\Ui\ConversionsPushedTile;
use Livewire\Livewire;
use Tests\TestCase;

class ConversionsPushedTileScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-139.conversions-pushed-tile'))->assertOk();

        Livewire::test(ConversionsPushedTile::class)->assertOk();
    }
}
