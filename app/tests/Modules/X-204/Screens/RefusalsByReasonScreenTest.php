<?php

namespace Tests\Modules\X204\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class RefusalsByReasonScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-204.refusals-by-reason'))->assertOk();

        Livewire::test(\App\Modules\X204\Ui\RefusalsByReason::class)->assertOk();
    }
}
