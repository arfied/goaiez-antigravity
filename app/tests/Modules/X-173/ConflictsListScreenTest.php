<?php

declare(strict_types=1);

namespace Tests\Modules\X173;

use App\Modules\X173\Actions\AccountingConnectAction;
use App\Modules\X173\Actions\AccountingSyncAction;
use App\Modules\X173\Models\AccountingSyncConflict;
use App\Modules\X173\Ui\ConflictsListView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ConflictsListScreenTest extends TestCase
{
    public function test_conflicts_list_lets_a_person_name_the_account_refuses_uncategorised_or_blank_and_never_reopens()
    {
        $bizB = self::provisionTenant();
        Tenancy::set($bizB->id);

        $connectAction = app(AccountingConnectAction::class);
        $syncAction = app(AccountingSyncAction::class);

        $connB = $connectAction->connect($bizB->id, 'xero', 'realm_x_b');
        $syncAction->syncTransactions($bizB->id, $connB->id, [
            ['ref' => 'inv_tx_b1', 'description' => 'desc', 'confidence' => 0.40, 'category' => 'guess'],
        ]);

        $bizA = self::provisionTenant();
        Tenancy::set($bizA->id);

        $connA = $connectAction->connect($bizA->id, 'quickbooks', 'realm_qb_4412');
        $syncAction->syncTransactions($bizA->id, $connA->id, [
            ['ref' => 'inv_tx_101', 'description' => 'desc1', 'confidence' => 0.95, 'category' => 'Job Revenue'],
            ['ref' => 'inv_tx_102', 'description' => 'desc2', 'confidence' => 0.62, 'category' => 'guess'],
            ['ref' => 'inv_tx_103', 'description' => 'desc3', 'confidence' => 0.30, 'category' => 'guess'],
        ]);

        $id102 = AccountingSyncConflict::where('business_id', $bizA->id)->where('transaction_ref', 'inv_tx_102')->first()->id;
        $id103 = AccountingSyncConflict::where('business_id', $bizA->id)->where('transaction_ref', 'inv_tx_103')->first()->id;

        Tenancy::forget();
        Livewire::test(ConflictsListView::class)->assertForbidden();
        Tenancy::set($bizA->id);

        $screen = Livewire::test(ConflictsListView::class)
            ->assertOk()
            ->assertSee('inv_tx_102')
            ->assertSee('62%')
            ->assertSee('inv_tx_103')
            ->assertSee('open')
            ->assertDontSee('inv_tx_101')
            ->assertDontSee('inv_tx_b1');

        $screen->set("resolutions.$id102", 'uncategorised')
            ->call('resolve', $id102)
            ->assertSee('is not a resolution');

        $this->assertSame('open', AccountingSyncConflict::find($id102)->status);
        $this->assertTrue(AccountingSyncConflict::find($id102)->flagged_for_review);

        $screen->set("resolutions.$id103", '')
            ->call('resolve', $id103)
            ->assertSee('is not a resolution');

        $this->assertSame('open', AccountingSyncConflict::find($id103)->status);
        $this->assertSame('uncategorised', AccountingSyncConflict::find($id103)->assigned_category);

        $screen->set("resolutions.$id102", 'Job Revenue')
            ->call('resolve', $id102)
            ->assertSee('now posts to Job Revenue')
            ->assertSee('Resolved by a person');

        $row102 = AccountingSyncConflict::find($id102);
        $this->assertSame('resolved', $row102->status);
        $this->assertSame('Job Revenue', $row102->assigned_category);
        $this->assertFalse($row102->flagged_for_review);

        $screen->set("resolutions.$id102", 'Other')
            ->call('resolve', $id102)
            ->assertSee('already resolved');

        $this->assertSame('Job Revenue', AccountingSyncConflict::find($id102)->assigned_category);

        Tenancy::set($bizB->id);
        $this->assertSame(1, AccountingSyncConflict::where('business_id', $bizB->id)->count());
        $this->assertSame('open', AccountingSyncConflict::where('business_id', $bizB->id)->first()->status);
    }

    public function test_empty_state_shows_the_truth()
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);

        Livewire::test(ConflictsListView::class)
            ->assertOk()
            ->assertSee('connecting waits on QuickBooks, Xero or Sage OAuth credentials that do not exist in this checkout')
            ->assertSee('two systems disagreeing about money is a human decision')
            ->assertDontSee('§141.5');
    }

    public function test_the_conflicts_screen_offers_to_record_the_account_and_never_to_post_to_it(): void
    {
        $bizA = self::provisionTenant();
        Tenancy::set($bizA->id);

        $connectAction = app(AccountingConnectAction::class);
        $syncAction = app(AccountingSyncAction::class);

        $connA = $connectAction->connect($bizA->id, 'quickbooks', 'realm_qb_4412');
        $syncAction->syncTransactions($bizA->id, $connA->id, [
            ['ref' => 'inv_tx_102', 'description' => 'desc2', 'confidence' => 0.62, 'category' => 'guess'],
        ]);

        Livewire::test(ConflictsListView::class)
            ->assertOk()
            ->assertSee('Record the account')
            ->assertDontSee('Post to this account');
    }
}
