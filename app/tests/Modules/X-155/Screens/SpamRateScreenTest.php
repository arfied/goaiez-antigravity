<?php

declare(strict_types=1);

namespace Tests\Modules\X155\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X155\Ui\SpamRate;
use Livewire\Livewire;
use Tests\TestCase;

class SpamRateScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-155.spam-rate'))->assertOk();

        Livewire::test(SpamRate::class)->assertOk();
    }
}
