<?php

declare(strict_types=1);

namespace Tests\Modules\X173\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X173\Models\AccountingConnection;
use App\Modules\X173\Models\SyncRun;
use App\Modules\X173\Ui\SyncErrorRateView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class SyncErrorRateViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-173.sync-error-rate'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No sync has run yet.');

        Tenancy::setUser($owner->id);
        $conn = AccountingConnection::create([
            'business_id' => $biz->id,
            'provider' => 'quickbooks',
            'realm_id' => 'Distinctive realm 4628',
            'is_active' => true,
        ]);
        SyncRun::create([
            'business_id' => $biz->id,
            'connection_id' => $conn->id,
            'records_synced' => 99,
            'conflicts_count' => 1,
            'status' => 'completed',
        ]);
        Tenancy::forget();

        $this->get(route('x-173.sync-error-rate'))
            ->assertOk()
            ->assertSee('100 lines seen')
            ->assertSee('1 conflicts')
            ->assertSee('99 lines categorised')
            ->assertSee('1% conflicts');

        Livewire::test(SyncErrorRateView::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-173.sync-error-rate.admin'))->assertOk();

        Livewire::test(SyncErrorRateView::class)->assertOk();
    }
}
