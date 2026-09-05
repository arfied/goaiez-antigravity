<?php

declare(strict_types=1);

namespace Tests\Modules\X173;

use App\Modules\X173\Models\AccountingConnection;
use App\Modules\X173\Models\AccountingSyncConflict;
use App\Modules\X173\Models\SyncRun;
use App\Modules\X173\Ui\SyncErrorRateView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class SyncErrorRateScreenTest extends TestCase
{
    public function test_sync_error_rate()
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);

        $conn = AccountingConnection::create([
            'business_id' => $biz->id,
            'provider' => 'xero'
        ]);

        $run1 = SyncRun::create([
            'business_id' => $biz->id,
            'connection_id' => $conn->id,
            'records_synced' => 0,
            'conflicts_count' => 0
        ]);

        $run2 = SyncRun::create([
            'business_id' => $biz->id,
            'connection_id' => $conn->id,
            'records_synced' => 3,
            'conflicts_count' => 1
        ]);
        
        AccountingSyncConflict::create([
            'business_id' => $biz->id,
            'sync_run_id' => $run2->id,
            'transaction_ref' => 'TX-99',
            'confidence_rate' => 0.5
        ]);

        Livewire::test(SyncErrorRateView::class)
            ->assertOk()
            ->assertSee('Run ' . $run1->id)
            ->assertSee('Run ' . $run2->id)
            ->assertSee('N/A') // run 1
            ->assertSee('25%') // run 2
            ->call('showConflicts', $run2->id)
            ->assertSee('Conflict: TX-99');
    }
}
