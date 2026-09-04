<?php

namespace Tests\Modules\X153\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

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
