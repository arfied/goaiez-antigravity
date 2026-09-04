<?php

declare(strict_types=1);

namespace Tests\Modules\X204\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class RefusalsByReasonScreenTest extends TestCase
{

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-204.refusals-by-reason.admin'))->assertOk();

        Livewire::test(\App\Modules\X204\Ui\RefusalsByReason::class)->assertOk();
    }
}
