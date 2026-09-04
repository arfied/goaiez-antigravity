<?php

namespace Tests\Modules\X161\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class DemoLedgerDailyScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-161.demo-ledger-daily'))->assertOk();

        Livewire::test(\App\Modules\X161\Ui\DemoLedgerDaily::class)->assertOk();
    }
}
