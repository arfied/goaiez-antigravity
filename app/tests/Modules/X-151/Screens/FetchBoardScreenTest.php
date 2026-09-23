<?php

declare(strict_types=1);

namespace Tests\Modules\X151\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X151\Ui\FetchBoard;
use Livewire\Livewire;
use Tests\TestCase;

class FetchBoardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-151.fetch-board'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console');

        Livewire::test(FetchBoard::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-151.fetch-board.admin'))->assertOk();

        Livewire::test(FetchBoard::class)->assertOk();
    }
}
