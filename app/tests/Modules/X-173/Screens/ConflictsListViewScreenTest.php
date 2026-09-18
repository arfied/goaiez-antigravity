<?php

declare(strict_types=1);

namespace Tests\Modules\X173\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X173\Models\AccountingConnection;
use App\Modules\X173\Models\AccountingSyncConflict;
use App\Modules\X173\Models\SyncRun;
use App\Modules\X173\Ui\ConflictsListView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ConflictsListViewScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-173.conflicts-list'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No line has ever been synced.');

        Tenancy::setUser($owner->id);
        $conn = AccountingConnection::create([
            'business_id' => $biz->id,
            'provider' => 'quickbooks',
            'realm_id' => 'Distinctive realm 4625',
            'is_active' => true,
        ]);
        $run = SyncRun::create([
            'business_id' => $biz->id,
            'connection_id' => $conn->id,
            'records_synced' => 99,
            'conflicts_count' => 1,
            'status' => 'completed',
        ]);
        AccountingSyncConflict::create([
            'business_id' => $biz->id,
            'sync_run_id' => $run->id,
            'transaction_ref' => 'Distinctive TXN 4627',
            'confidence_rate' => 0.5,
            'assigned_category' => 'uncategorised',
            'flagged_for_review' => true,
            'status' => 'open',
        ]);
        Tenancy::forget();

        $this->get(route('x-173.conflicts-list'))
            ->assertOk()
            ->assertSee('Distinctive TXN 4627')
            ->assertSee('50%')
            ->assertSee('Record the account')
            ->assertDontSee('No line has ever been synced.');

        Livewire::test(ConflictsListView::class)->assertOk();
    }

    public function test_screen_renders_for_admin(): void
    {
        $user = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($user);
        $biz = $this->provisionTenant(['owner_user_id' => $user->id]);

        $this->get(route('x-173.conflicts-list.admin'))->assertOk();

        Livewire::test(ConflictsListView::class)->assertOk();
    }
}
