<?php

declare(strict_types=1);

namespace Tests\Modules\X191\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X191\Ui\LinksEarned;
use Livewire\Livewire;
use Tests\TestCase;

class LinksEarnedScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-191.links-earned'))->assertOk();

        Livewire::test(LinksEarned::class)->assertOk();
    }
}
