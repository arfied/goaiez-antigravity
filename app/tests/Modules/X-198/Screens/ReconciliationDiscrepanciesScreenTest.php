<?php

declare(strict_types=1);

namespace Tests\Modules\X198\Screens;

use App\Enums\UserRole;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

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
