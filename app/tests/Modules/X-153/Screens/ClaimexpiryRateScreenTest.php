<?php

declare(strict_types=1);

namespace Tests\Modules\X153\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class ClaimexpiryRateScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-153.claimexpiry-rate'))->assertOk();

        Livewire::test(\App\Modules\X153\Ui\ClaimexpiryRate::class)->assertOk();
    }
}
