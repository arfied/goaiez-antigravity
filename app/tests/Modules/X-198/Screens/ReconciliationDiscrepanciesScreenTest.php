<?php

namespace Tests\Modules\X198\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ReconciliationDiscrepanciesScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-198.reconciliation-discrepancies'))->assertOk();

        Livewire::test(\App\Modules\X198\Ui\ReconciliationDiscrepancies::class)->assertOk();
    }
}
