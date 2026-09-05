<?php

declare(strict_types=1);

namespace Tests\Modules\X173;

use App\Modules\X173\Models\AccountMapping;
use App\Modules\X173\Models\AccountingConnection;
use App\Modules\X173\Ui\ConnectionMappingView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ConnectionMappingScreenTest extends TestCase
{
    public function test_connection_mapping()
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);

        $screen = Livewire::test(ConnectionMappingView::class)
            ->assertOk()
            ->call('connect', 'xero')
            ->assertSee('Waiting on xero OAuth');

        $this->assertSame(0, AccountingConnection::where('business_id', $biz->id)->count());

        $connection = AccountingConnection::create([
            'business_id' => $biz->id,
            'provider' => 'xero',
            'is_active' => false,
        ]);

        $screen = Livewire::test(ConnectionMappingView::class)
            ->set('internalCategory', 'software')
            ->set('remoteGlAccountId', '200')
            ->set('remoteGlAccountName', 'Sales')
            ->call('mapAccount', $connection->id)
            ->assertSee('inactive connection refused');
            
        $connection->update(['is_active' => true]);

        $screen->set('internalCategory', '')
            ->call('mapAccount', $connection->id)
            ->assertSee('blank parts refused');

        $screen->set('internalCategory', 'software')
            ->call('mapAccount', $connection->id)
            ->assertSee('mapped');
            
        $this->assertSame(1, AccountMapping::where('business_id', $biz->id)->count());
        
        $screen->set('remoteGlAccountName', 'Sales - Software')
            ->call('mapAccount', $connection->id)
            ->assertSee('mapped');
            
        $this->assertSame(1, AccountMapping::where('business_id', $biz->id)->count(), 'count-is-1 line');
        $this->assertSame('Sales - Software', AccountMapping::first()->remote_gl_account_name);
    }
}
