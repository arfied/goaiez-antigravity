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
        $biz = $this->provisionTenant();
        $owner = User::where('business_id', $biz->id)->first();
        $owner->role = UserRole::Owner;
        $owner->save();
        $this->actingAs($owner);

        $this->get(route('x-182.connected-accounts'))->assertOk();

        Livewire::test(\App\Modules\X182\Ui\ConnectedAccounts::class)->assertOk();
    }
}
