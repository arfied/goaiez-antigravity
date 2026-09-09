<?php

declare(strict_types=1);

namespace Tests\Modules\X173;

use App\Modules\X173\Actions\AccountingConnectAction;
use App\Modules\X173\Actions\AccountingMapAction;
use App\Modules\X173\Models\AccountingConnection;
use App\Modules\X173\Models\AccountMapping;
use App\Modules\X173\Ui\ConnectionMappingView;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ConnectionMappingScreenTest extends TestCase
{
    public function test_connection_mapping_maps_one_category_to_one_account_replaces_on_remap_and_never_connects_without_credentials()
    {
        $bizB = self::provisionTenant();
        Tenancy::set($bizB->id);

        $connectAction = app(AccountingConnectAction::class);
        $mapAction = app(AccountingMapAction::class);

        $connB = $connectAction->connect($bizB->id, 'xero', 'realm_x_b', 'oauth_x_b_fixture');
        $mapAction->mapAccount($bizB->id, $connB->id, 'Parts', 'gl_5000', 'COGS');

        $bA = self::provisionTenant()->id;
        Tenancy::set($bA);

        $connA = $connectAction->connect($bA, 'quickbooks', 'realm_qb_4412', 'oauth_qb_4412_fixture');
        $connId = $connA->id;

        Tenancy::forget();
        Livewire::test(ConnectionMappingView::class)->assertForbidden();
        Tenancy::set($bA);

        $screen = Livewire::test(ConnectionMappingView::class)
            ->assertOk()
            ->assertSee('quickbooks')
            ->assertSee('realm_qb_4412')
            ->assertSee('Nothing mapped yet')
            ->assertDontSee('realm_x_b')
            ->assertDontSee('COGS');

        $screen->set("map.$connId.category", 'Job Revenue')
            ->set("map.$connId.glId", 'gl_4000')
            ->set("map.$connId.glName", 'HVAC Service Income')
            ->call('mapAccount', $connId)
            ->assertSee('is recorded against gl_4000')
            ->assertSee('Nothing was posted')
            ->assertDontSee('now posts to');

        $screen->set("map.$connId.category", 'Job Revenue')
            ->set("map.$connId.glId", 'gl_4100')
            ->set("map.$connId.glName", 'Service Income')
            ->call('mapAccount', $connId)
            ->assertSee('gl_4100')
            ->assertDontSee('gl_4000');

        $this->assertSame(1, AccountMapping::where('business_id', $bA)->where('connection_id', $connId)->where('internal_category', 'Job Revenue')->count(), 'a remap replaces, it never duplicates');

        $screen->set("map.$connId.category", 'Job Revenue')
            ->set("map.$connId.glId", '')
            ->set("map.$connId.glName", 'Service Income')
            ->call('mapAccount', $connId)
            ->assertSee('nothing was saved');

        $this->assertSame(1, AccountMapping::where('business_id', $bA)->count());

        AccountingConnection::whereKey($connId)->update(['is_active' => false]);

        $screen->set("map.$connId.glId", 'gl_4200')
            ->call('mapAccount', $connId)
            ->assertSee('A connection goes live only when the provider OAuth returns a credential');

        $this->assertSame(1, AccountMapping::where('business_id', $bA)->count());

        $screen->set('provider', 'xero')
            ->call('connect')
            ->assertSee('Waiting on xero OAuth')
            ->assertSee('The one-click connect lands when the owner grants them')
            ->assertDontSee('§141.5');

        $this->assertSame(1, AccountingConnection::where('business_id', $bA)->count());

        Tenancy::set($bizB->id);
        $this->assertSame(1, AccountingConnection::where('business_id', $bizB->id)->count());
        $this->assertSame(1, AccountMapping::where('business_id', $bizB->id)->where('internal_category', 'Parts')->count());
    }

    public function test_connection_mapping_empty_state_names_oauth_and_claims_no_ai()
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);

        Livewire::test(ConnectionMappingView::class)
            ->assertOk()
            ->assertSee('none exist in this checkout, so the Connect button below')
            ->assertSee('maps each category to one ledger account')
            ->assertDontSee('§30.5');
    }

    public function test_a_ledger_connection_with_no_credential_is_not_active_and_refuses_a_mapping()
    {
        $biz = self::provisionTenant();
        Tenancy::set($biz->id);

        $conn = app(AccountingConnectAction::class)->connect($biz->id, 'quickbooks', 'realm_qb_9001');

        $this->assertNull($conn->fresh()->access_token);
        $this->assertFalse($conn->fresh()->is_active, 'a connection with no credential is not active');

        Livewire::test(ConnectionMappingView::class)
            ->assertOk()
            ->assertSee('realm_qb_9001')
            ->assertSee('inactive')
            ->set("map.{$conn->id}.category", 'Job Revenue')
            ->set("map.{$conn->id}.glId", 'gl_4000')
            ->set("map.{$conn->id}.glName", 'HVAC Service Income')
            ->call('mapAccount', $conn->id)
            ->assertSee('A connection goes live only when the provider OAuth returns a credential');

        $this->assertSame(0, AccountMapping::where('business_id', $biz->id)->count());
    }

    public function test_the_mappings_list_holds_its_order_when_a_row_is_rewritten(): void
    {
        $bizId = self::provisionTenant()->id;
        Tenancy::set($bizId);

        $connectAction = app(AccountingConnectAction::class);
        $mapAction = app(AccountingMapAction::class);

        $conn = $connectAction->connect($bizId, 'quickbooks', 'realm_qb_123', 'oauth_token_fixture');
        $connId = $conn->id;

        $mapAction->mapAccount($bizId, $connId, 'Revenue', 'gl_1', 'Remote 1');
        $mapAction->mapAccount($bizId, $connId, 'Materials', 'gl_2', 'Remote 2');
        $mapAction->mapAccount($bizId, $connId, 'Subcontractors', 'gl_3', 'Remote 3');

        $mapAction->mapAccount($bizId, $connId, 'Revenue', 'gl_9999', 'Ledger Income');

        Livewire::test(ConnectionMappingView::class)
            ->assertSeeInOrder(['Revenue', 'Materials', 'Subcontractors']);
    }
}
