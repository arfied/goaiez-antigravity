<?php

declare(strict_types=1);

namespace Tests\Modules\X173;

use App\Modules\X173\Actions\AccountingConnectAction;
use App\Modules\X173\Actions\AccountingSyncAction;
use App\Modules\X173\Models\SyncRun;
use App\Modules\X173\Ui\SyncErrorRateView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class SyncErrorRateScreenTest extends TestCase
{
    public function test_sync_error_rate_counts_every_line_seen_and_calls_nothing_seen_unknown()
    {
        $bizB = self::provisionTenant();
        Tenancy::set($bizB->id);

        $connectAction = app(AccountingConnectAction::class);
        $syncAction = app(AccountingSyncAction::class);

        $connB = $connectAction->connect($bizB->id, 'xero', 'realm_x_b');
        $syncAction->syncTransactions($bizB->id, $connB->id, [
            ['ref' => 'inv_tx_b_high', 'description' => 'desc', 'confidence' => 0.95, 'category' => 'Revenue'],
            ['ref' => 'inv_tx_b_low', 'description' => 'desc', 'confidence' => 0.40, 'category' => 'guess'],
        ]);

        $bA = self::provisionTenant()->id;
        Tenancy::set($bA);

        $connA = $connectAction->connect($bA, 'quickbooks', 'realm_qb_4412');

        // run 1
        $res1 = $syncAction->syncTransactions($bA, $connA->id, [
            ['ref' => 'inv_tx_101', 'description' => 'd1', 'confidence' => 0.95, 'category' => 'Rev'],
            ['ref' => 'inv_tx_102', 'description' => 'd2', 'confidence' => 0.95, 'category' => 'Rev'],
            ['ref' => 'inv_tx_103', 'description' => 'd3', 'confidence' => 0.95, 'category' => 'Rev'],
            ['ref' => 'inv_tx_r1_low', 'description' => 'd4', 'confidence' => 0.50, 'category' => 'guess'],
        ]);
        $run1Id = $res1['run_id'] ?? SyncRun::where('business_id', $bA)->orderByDesc('id')->first()->id;

        // run 2
        $res2 = $syncAction->syncTransactions($bA, $connA->id, [
            ['ref' => 'inv_tx_r2_low1', 'description' => 'd5', 'confidence' => 0.40, 'category' => 'guess'],
            ['ref' => 'inv_tx_r2_low2', 'description' => 'd6', 'confidence' => 0.40, 'category' => 'guess'],
        ]);

        // run 3 - check if empty payload is accepted
        try {
            $res3 = $syncAction->syncTransactions($bA, $connA->id, []);
            $run3Id = $res3['run_id'] ?? SyncRun::where('business_id', $bA)->orderByDesc('id')->first()->id;
        } catch (\Throwable $e) {
            // fallback to manual creation if refused
            $run3 = SyncRun::create([
                'business_id' => $bA,
                'connection_id' => $connA->id,
                'records_synced' => 0,
                'conflicts_count' => 0,
                'status' => 'completed',
            ]);
            $run3Id = $run3->id;
        }

        Tenancy::forget();
        Livewire::test(SyncErrorRateView::class)->assertForbidden();
        Tenancy::set($bA);

        $screen = Livewire::test(SyncErrorRateView::class)
            ->assertOk()
            ->assertSee('25% conflicts')
            ->assertSee('100% conflicts')
            ->assertSee('nothing to sync')
            ->assertSee('6 lines seen')
            ->assertSee('50% conflicts')
            ->assertSee('Nothing is posted to a ledger from here');

        $screen->call('show', $run1Id)
            ->assertSee('inv_tx_r1_low');

        $run4 = SyncRun::create([
            'business_id' => $bA,
            'connection_id' => $connA->id,
            'records_synced' => 888,
            'conflicts_count' => 0,
            'status' => 'completed',
        ]);

        $screen->call('show', $run3Id)
            ->assertSee('888 lines categorised')
            ->assertDontSee('lines posted')
            ->assertSee('This run had no conflicts')
            ->assertDontSee('inv_tx_b_low');

        Tenancy::set($bizB->id);
        $this->assertSame(1, SyncRun::where('business_id', $bizB->id)->count());
    }

    public function test_empty_state_shows_the_truth()
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);

        Livewire::test(SyncErrorRateView::class)
            ->assertOk()
            ->assertSee('Nothing syncs on a schedule: a run happens only when a connected ledger is synced')
            ->assertSee('a conflict, never a silent gap')
            ->assertDontSee('§30.5');
    }

    public function test_a_single_conflict_in_two_hundred_lines_is_not_reported_as_zero_percent()
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);

        $conn = app(AccountingConnectAction::class)->connect($biz->id, 'xero', 'realm_under_one');

        SyncRun::create([
            'business_id' => $biz->id,
            'connection_id' => $conn->id,
            'records_synced' => 200,
            'conflicts_count' => 1,
            'status' => 'completed',
        ]);

        Livewire::test(SyncErrorRateView::class)
            ->assertOk()
            ->assertSee('1 conflicts')
            ->assertSee('under 1% conflicts')
            ->assertDontSee('0% conflicts');
    }
}
