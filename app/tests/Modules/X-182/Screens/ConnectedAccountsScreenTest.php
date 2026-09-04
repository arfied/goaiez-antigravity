<?php

namespace Tests\Modules\X182\Screens;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\User;
use App\Enums\UserRole;

class ConnectedAccountsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-182.connected-accounts'))->assertOk();

        Livewire::test(\App\Modules\X182\Ui\ConnectedAccounts::class)->assertOk();
    }
}
