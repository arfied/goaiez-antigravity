<?php

namespace Tests\Modules\X199\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class MoneyPaidTodayScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-199.money-paid-today'))->assertOk();

        Livewire::test(\App\Modules\X199\Ui\MoneyPaidToday::class)->assertOk();
    }
}
