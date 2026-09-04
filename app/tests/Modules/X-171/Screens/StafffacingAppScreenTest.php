<?php

declare(strict_types=1);

namespace Tests\Modules\X171\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X171\Ui\StafffacingApp;
use Livewire\Livewire;
use Tests\TestCase;

class StafffacingAppScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-171.stafffacing-app'))->assertOk();

        Livewire::test(StafffacingApp::class)->assertOk();
    }
}
