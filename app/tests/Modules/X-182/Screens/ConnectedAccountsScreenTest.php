<?php

declare(strict_types=1);

namespace Tests\Modules\X182\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X182\Ui\ConnectedAccounts;
use Livewire\Livewire;
use Tests\TestCase;

class ConnectedAccountsScreenTest extends TestCase
{
    public function test_screen_renders(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-182.connected-accounts'))->assertOk();

        Livewire::test(ConnectedAccounts::class)->assertOk();
    }
}
