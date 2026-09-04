<?php

declare(strict_types=1);

namespace Tests\Modules\X147\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X147\Ui\DegradeRatePer;
use Livewire\Livewire;
use Tests\TestCase;

class DegradeRatePerScreenTest extends TestCase
{
    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-147.degrade-rate-per.admin'))->assertOk();

        Livewire::test(DegradeRatePer::class)->assertOk();
    }
}
