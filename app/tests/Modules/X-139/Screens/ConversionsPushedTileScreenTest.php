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
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-139.conversions-pushed-tile'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('<h1 class="sr-only">Uploaded Conversions</h1>', false);

        Livewire::test(ConversionsPushedTile::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-139.conversions-pushed-tile.admin'))->assertOk();

        Livewire::test(ConversionsPushedTile::class)->assertOk();
    }
}
