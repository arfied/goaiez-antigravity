<?php

declare(strict_types=1);

namespace Tests\Modules\X161\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X161\Ui\DemoLedgerDaily;
use Livewire\Livewire;
use Tests\TestCase;

class DemoLedgerDailyScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);

        $this->get(route('x-161.demo-ledger-daily'))->assertOk();

        Livewire::test(DemoLedgerDaily::class)->assertOk();
    }
}
