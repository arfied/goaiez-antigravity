<?php

declare(strict_types=1);

namespace Tests\Modules\X182\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X182\Models\SocialAccount;
use App\Modules\X182\Ui\ConnectedAccounts;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ConnectedAccountsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-182.connected-accounts'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No social accounts connected yet.');

        Tenancy::set((int) $biz->id);
        SocialAccount::create([
            'business_id' => $biz->id,
            'platform' => 'instagram',
            'account_handle' => '@distinctive_handle_4473',
            'is_connected' => true,
        ]);
        Tenancy::forget();

        $this->get(route('x-182.connected-accounts'))
            ->assertOk()
            ->assertSee('@distinctive_handle_4473')
            ->assertSee('instagram')
            ->assertSee('connected')
            ->assertDontSee('No social accounts connected yet.');

        Livewire::actingAs($owner)->test(ConnectedAccounts::class, ['businessId' => $biz->id])->assertOk();
    }
}
