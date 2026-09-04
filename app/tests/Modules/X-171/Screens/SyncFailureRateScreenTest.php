<?php

declare(strict_types=1);

namespace Tests\Modules\X171\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class SyncFailureRateScreenTest extends TestCase
{

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-171.sync-failure-rate.admin'))->assertOk();

        Livewire::test(\App\Modules\X171\Ui\SyncFailureRate::class)->assertOk();
    }
}
