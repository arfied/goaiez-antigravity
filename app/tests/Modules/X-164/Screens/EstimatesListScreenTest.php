<?php

declare(strict_types=1);

namespace Tests\Modules\X164\Screens;

use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Enums\UserRole;
use App\Jobs\DeliverPlatformMail;
use App\Models\Customer;
use App\Models\User;
use App\Modules\X121\Models\Person;
use App\Modules\X164\Actions\EstimateDraftAction;
use App\Modules\X164\Models\Estimate;
use App\Modules\X164\Models\EstimateLine;
use App\Modules\X164\Models\EstimateVersion;
use App\Modules\X164\Ui\EstimatesList;
use App\Services\Billing\CreditLedger;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class EstimatesListScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-164.estimates-list'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No estimates yet')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );
        app(EstimateDraftAction::class)->handle((int) $biz->id, null, [
            ['service_name' => 'Gutter cleaning', 'quantity' => 3, 'unit_price_cents' => 4175],
        ]);
        Tenancy::forget();

        $this->get(route('x-164.estimates-list'))
            ->assertOk()
            ->assertSee('$125.25')
            ->assertDontSee('No estimates yet');

        Livewire::test(EstimatesList::class)->assertOk();
    }

    public function test_can_draft_estimate(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Gutter cleaning')
            ->set('quantity', 2)
            ->set('unitPriceCents', 5000)
            ->call('draftEstimate')
            ->assertSet('serviceName', '')
            ->assertSet('quantity', 1)
            ->assertSet('unitPriceCents', 0);

        $estimateTable = (new Estimate)->getTable();
        $lineTable = (new EstimateLine)->getTable();

        $this->assertDatabaseHas($estimateTable, ['status' => 'draft', 'business_id' => $biz->id]);
        $this->assertDatabaseHas($lineTable, [
            'service_name' => 'Gutter cleaning',
            'subtotal_cents' => 2 * 5000,
        ]);
    }

    public function test_screen_shows_drafted_estimate(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Window washing')
            ->set('quantity', 1)
            ->set('unitPriceCents', 15000)
            ->call('draftEstimate');

        $estimate = Estimate::where('business_id', $biz->id)->first();
        $estimateNumber = $estimate->estimate_number;

        Tenancy::forget();

        $this->get(route('x-164.estimates-list'))
            ->assertOk()
            ->assertSee($estimateNumber)
            ->assertDontSee('No estimates yet');
    }

    public function test_refuses_empty_service_name(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->set('serviceName', '')
            ->set('quantity', 2)
            ->set('unitPriceCents', 5000)
            ->call('draftEstimate')
            ->assertSet('error', 'Service name cannot be empty.');

        $estimateTable = (new Estimate)->getTable();
        $this->assertDatabaseMissing($estimateTable, ['business_id' => $biz->id]);
    }

    public function test_refuses_quantity_below_one(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Roof repair')
            ->set('quantity', 0)
            ->set('unitPriceCents', 5000)
            ->call('draftEstimate')
            ->assertSet('error', 'Quantity must be at least 1.');

        $estimateTable = (new Estimate)->getTable();
        $this->assertDatabaseMissing($estimateTable, ['business_id' => $biz->id]);
    }

    public function test_can_mark_an_estimate_sent(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Gutter cleaning')
            ->set('quantity', 1)
            ->set('unitPriceCents', 5000)
            ->call('draftEstimate');

        $estimate = Estimate::where('business_id', $biz->id)->first();
        $estimateNumber = $estimate->estimate_number;
        $id = $estimate->id;

        Livewire::test(EstimatesList::class)
            ->call('sendEstimate', $id)
            ->assertSet('error', 'Not sent — this estimate has no customer email.');

        $estimateTable = (new Estimate)->getTable();
        $this->assertDatabaseHas($estimateTable, ['id' => $id, 'status' => 'draft', 'business_id' => $biz->id]);
    }

    public function test_estimates_list_shows_a_sent_estimate(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Gutter cleaning')
            ->set('quantity', 1)
            ->set('unitPriceCents', 5000)
            ->call('draftEstimate');

        $est = Estimate::where('business_id', $biz->id)->first();
        $id = $est->id;

        $est->update(['status' => 'sent']);

        $est->refresh();

        Tenancy::forget();

        $this->actingAs($owner);

        $row = $est->estimate_number.' · $'.number_format($est->total_cents / 100, 2);

        $this->get(route('x-164.estimates-list'))
            ->assertOk()
            ->assertSee($row.' · Sent')
            ->assertDontSee($row.' · Draft');
    }

    public function test_marking_another_tenants_estimate_sent_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        $this->actingAs($ownerB);
        Tenancy::set((int) $bizB->id);

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Gutter cleaning')
            ->set('quantity', 1)
            ->set('unitPriceCents', 5000)
            ->call('draftEstimate');

        $estB = Estimate::where('business_id', $bizB->id)->first();
        $bEstimateId = $estB->id;

        $this->actingAs($ownerA);
        Tenancy::set((int) $bizA->id);

        Livewire::test(EstimatesList::class)
            ->call('sendEstimate', $bEstimateId)
            ->assertSet('error', 'That estimate is not here any more.');
    }

    public function test_can_accept_a_sent_estimate(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Culvert Relining 8309')
            ->set('quantity', 1)
            ->set('unitPriceCents', 830900)
            ->call('draftEstimate');

        $est = Estimate::where('business_id', $biz->id)->first();
        $est->update(['status' => 'sent']);

        $component = Livewire::test(EstimatesList::class)
            ->set('customerSignature', 'Marisol Quintero')
            ->call('acceptEstimate', $est->id)
            ->assertSet('error', null);

        $successMsg = $component->get('success');
        $this->assertStringContainsString('is signed by Marisol Quintero', $successMsg);
        $this->assertStringContainsString('price-book version is frozen', $successMsg);
        $this->assertStringContainsString('No deposit has been requested and no money has moved', $successMsg);
        $this->assertStringContainsString('signed PDF is not produced yet', $successMsg);

        $this->assertDatabaseHas((new Estimate)->getTable(), [
            'id' => $est->id,
            'status' => 'accepted',
            'signed_by_customer' => 'Marisol Quintero',
        ]);
    }

    public function test_the_list_reads_accepted_and_the_accept_button_goes(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Culvert Relining 8309')
            ->set('quantity', 1)
            ->set('unitPriceCents', 830900)
            ->call('draftEstimate');

        $est = Estimate::where('business_id', $biz->id)->first();
        $est->update(['status' => 'sent']);

        Tenancy::forget();
        $this->get(route('x-164.estimates-list'))
            ->assertOk()
            ->assertSee('Sent')
            ->assertSee('acceptEstimate(');

        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );
        Livewire::test(EstimatesList::class)
            ->set('customerSignature', 'Marisol Quintero')
            ->call('acceptEstimate', $est->id);

        Tenancy::forget();
        $this->get(route('x-164.estimates-list'))
            ->assertOk()
            ->assertSee('Accepted')
            ->assertSee('8,309.00')
            ->assertDontSee('acceptEstimate(');
    }

    public function test_the_frozen_snapshot_records_the_price_book_version(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Culvert Relining 8309')
            ->set('quantity', 1)
            ->set('unitPriceCents', 830900)
            ->call('draftEstimate');

        $est = Estimate::where('business_id', $biz->id)->first();
        $est->update(['status' => 'sent']);

        Livewire::test(EstimatesList::class)->set('customerSignature', 'Marisol Quintero')->call('acceptEstimate', $est->id);

        // No UI reads this table
        $this->assertDatabaseHas((new EstimateVersion)->getTable(), ['estimate_id' => $est->id]);
    }

    public function test_an_expired_estimate_is_refused_and_reads_expired(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Culvert Relining 8309')
            ->set('quantity', 1)
            ->set('unitPriceCents', 830900)
            ->call('draftEstimate');

        $est = Estimate::where('business_id', $biz->id)->first();
        $est->update(['status' => 'sent']);

        $est->update(['expires_at' => now()->subDay()]);

        Livewire::test(EstimatesList::class)
            ->set('customerSignature', 'Marisol Quintero')
            ->call('acceptEstimate', $est->id)
            ->assertSet('error', 'That estimate had already expired, so it was not accepted — and it now reads Expired in the list. Draft a fresh one.');

        $this->assertDatabaseHas((new Estimate)->getTable(), ['id' => $est->id, 'status' => 'expired']);

        Tenancy::forget();
        $this->get(route('x-164.estimates-list'))->assertOk()->assertSee('Expired');
    }

    public function test_accepting_without_a_signature_is_refused(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Culvert Relining 8309')
            ->set('quantity', 1)
            ->set('unitPriceCents', 830900)
            ->call('draftEstimate');

        $est = Estimate::where('business_id', $biz->id)->first();
        $est->update(['status' => 'sent']);

        Livewire::test(EstimatesList::class)
            ->set('customerSignature', '')
            ->call('acceptEstimate', $est->id)
            ->assertSet('error', 'Type the customer’s name as their signature before accepting.');

        $this->assertDatabaseHas((new Estimate)->getTable(), ['id' => $est->id, 'status' => 'sent']);
    }

    public function test_accepting_another_tenants_estimate_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);
        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        Tenancy::setUser($ownerB->id);
        $this->actingAs($ownerB);
        Tenancy::set((int) $bizB->id);
        $this->actingAs($ownerB);

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Culvert Relining 8309')
            ->set('quantity', 1)
            ->set('unitPriceCents', 830900)
            ->call('draftEstimate');

        $estB = Estimate::where('business_id', $bizB->id)->first();
        $estB->update(['status' => 'sent']);

        Tenancy::setUser($ownerA->id);
        $this->actingAs($ownerA);
        Tenancy::set((int) $bizA->id);
        $this->actingAs($ownerA);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(EstimatesList::class)->set('customerSignature', 'Marisol Quintero')->call('acceptEstimate', $estB->id);
    }

    public function test_can_refresh_an_expired_estimate(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Bore Lining')->set('quantity', 1)->set('unitPriceCents', 612755)
            ->call('draftEstimate');
        $est = Estimate::where('business_id', $biz->id)->firstOrFail();
        $est->update(['status' => 'sent']);
        $est->update(['expires_at' => now()->subDay()]);
        Livewire::test(EstimatesList::class)
            ->set('customerSignature', 'Ingrid Halvorsen')->call('acceptEstimate', $est->id);
        // status is now 'expired'

        $component = Livewire::test(EstimatesList::class)
            ->set('refreshedUnitPriceCents', '491820')
            ->call('refreshEstimate', $est->id)
            ->assertSet('error', null);

        $successMsg = $component->get('success');
        $this->assertStringContainsString('is back to Sent at $4,918.20', $successMsg);
        $this->assertStringContainsString('book version 2', $successMsg);
        $this->assertStringContainsString('nothing has been charged', $successMsg);

        $this->assertDatabaseHas((new Estimate)->getTable(), [
            'id' => $est->id,
            'status' => 'sent',
            'total_cents' => 491820,
            'price_book_version' => 2,
        ]);
    }

    public function test_the_line_is_updated_too_so_the_header_and_lines_agree(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Bore Lining')->set('quantity', 1)->set('unitPriceCents', 612755)
            ->call('draftEstimate');
        $est = Estimate::where('business_id', $biz->id)->firstOrFail();
        $est->update(['status' => 'sent']);
        $est->update(['expires_at' => now()->subDay()]);
        Livewire::test(EstimatesList::class)
            ->set('customerSignature', 'Ingrid Halvorsen')->call('acceptEstimate', $est->id);

        Livewire::test(EstimatesList::class)
            ->set('refreshedUnitPriceCents', '491820')
            ->call('refreshEstimate', $est->id);

        // The action matches lines by service_name, so this assertion proves the control passed the row's own name and not a typed one.
        $this->assertDatabaseHas((new EstimateLine)->getTable(), [
            'estimate_id' => $est->id,
            'unit_price_cents' => 491820,
            'subtotal_cents' => 491820,
        ]);
    }

    public function test_the_list_shows_the_new_total_and_the_refresh_button_goes(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Bore Lining')->set('quantity', 1)->set('unitPriceCents', 612755)
            ->call('draftEstimate');
        $est = Estimate::where('business_id', $biz->id)->firstOrFail();
        $est->update(['status' => 'sent']);
        $est->update(['expires_at' => now()->subDay()]);
        Livewire::test(EstimatesList::class)
            ->set('customerSignature', 'Ingrid Halvorsen')->call('acceptEstimate', $est->id);

        Tenancy::forget();
        $this->get(route('x-164.estimates-list'))
            ->assertOk()
            ->assertSee('Expired')
            ->assertSee('6,127.55')
            ->assertSee('refreshEstimate(');

        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );
        Livewire::test(EstimatesList::class)
            ->set('refreshedUnitPriceCents', '491820')
            ->call('refreshEstimate', $est->id);

        Tenancy::forget();
        $this->get(route('x-164.estimates-list'))
            ->assertOk()
            ->assertSee('Sent')
            ->assertSee('4,918.20')
            ->assertDontSee('6,127.55')
            ->assertDontSee('refreshEstimate(');
    }

    public function test_refreshing_a_sent_estimate_is_refused(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Bore Lining')->set('quantity', 1)->set('unitPriceCents', 612755)
            ->call('draftEstimate');
        $est = Estimate::where('business_id', $biz->id)->firstOrFail();
        $est->update(['status' => 'sent']);

        Livewire::test(EstimatesList::class)
            ->set('refreshedUnitPriceCents', '491820')
            ->call('refreshEstimate', $est->id)
            ->assertSet('error', 'Only an expired estimate can be refreshed. This one reads Sent.');

        $this->assertDatabaseHas((new Estimate)->getTable(), [
            'id' => $est->id,
            'price_book_version' => 1,
        ]);

        Livewire::test(EstimatesList::class)
            ->set('refreshedUnitPriceCents', '')
            ->call('refreshEstimate', $est->id)
            ->assertSet('error', 'Type the new unit price in whole cents before refreshing.');
    }

    public function test_refreshing_another_tenants_estimate_is_refused(): void
    {
        $ownerB = User::factory()->create(['role' => UserRole::Owner]);
        $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

        $ownerA = User::factory()->create(['role' => UserRole::Owner]);
        $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

        Tenancy::setUser($ownerB->id);
        $this->actingAs($ownerB);
        Tenancy::set((int) $bizB->id);
        $this->actingAs($ownerB);

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Bore Lining')->set('quantity', 1)->set('unitPriceCents', 612755)
            ->call('draftEstimate');
        $estB = Estimate::where('business_id', $bizB->id)->firstOrFail();
        $estB->update(['status' => 'sent']);
        $estB->update(['expires_at' => now()->subDay()]);
        Livewire::test(EstimatesList::class)
            ->set('customerSignature', 'Ingrid Halvorsen')->call('acceptEstimate', $estB->id);

        Tenancy::setUser($ownerA->id);
        $this->actingAs($ownerA);
        Tenancy::set((int) $bizA->id);
        $this->actingAs($ownerA);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(EstimatesList::class)->set('refreshedUnitPriceCents', '491820')->call('refreshEstimate', $estB->id);
    }

    public function test_copy_portal_link_mints_a_link_the_customer_can_open_without_logging_in(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );
        $estimate = app(EstimateDraftAction::class)->handle((int) $biz->id, null, [
            ['service_name' => 'Gutter cleaning', 'quantity' => 3, 'unit_price_cents' => 4175],
        ]);
        $estimate->update(['status' => 'sent']);

        $component = Livewire::test(EstimatesList::class)
            ->call('portalLink', $estimate->id)
            ->assertHasNoErrors()
            ->assertSet('portalUrl.'.$estimate->id, fn ($v) => is_string($v) && str_contains($v, '/portal/'));

        $this->assertDatabaseHas('portal_links', [
            'business_id' => $biz->id,
            'resource_type' => 'estimate',
            'resource_id' => $estimate->id,
            'is_active' => true,
        ]);

        $url = $component->get('portalUrl')[$estimate->id];

        Tenancy::forgetAll();
        auth()->logout();

        $this->get($url)->assertOk();
    }

    public function test_sixty81_success(): void
    {
        Bus::fake();
        customerMailIsPermitted();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        $person = Person::create(['business_id' => $biz->id, 'email' => 'est-8371@example.test']);
        $customer = Customer::factory()->create(['business_id' => $biz->id, 'email' => 'est-8371@example.test']);
        permitFor($customer);

        $estimate = app(EstimateDraftAction::class)->handle((int) $biz->id, $person->id, [
            ['service_name' => 'Gutter cleaning', 'quantity' => 1, 'unit_price_cents' => 5000],
        ]);

        Livewire::test(EstimatesList::class)
            ->call('sendEstimate', $estimate->id)
            ->assertSet('error', null)
            ->assertSet('success', 'Emailed estimate '.$estimate->estimate_number.' to est-8371@example.test.');

        $this->assertDatabaseHas('estimates', ['id' => $estimate->id, 'status' => 'sent']);
        $this->assertDatabaseHas('outreach_messages', ['customer_id' => $customer->id, 'channel' => 'email']);
        Bus::assertDispatched(DeliverPlatformMail::class);
    }

    public function test_sixty81_no_customer_row(): void
    {
        Bus::fake();
        customerMailIsPermitted();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        $person = Person::create(['business_id' => $biz->id, 'email' => 'est-8371-2@example.test']);

        $estimate = app(EstimateDraftAction::class)->handle((int) $biz->id, $person->id, [
            ['service_name' => 'Gutter cleaning', 'quantity' => 1, 'unit_price_cents' => 5000],
        ]);

        Livewire::test(EstimatesList::class)
            ->call('sendEstimate', $estimate->id)
            ->assertSet('success', null)
            ->assertSet('error', 'Not sent — nobody with that email is in your customer list. Add them as a customer first.');

        $this->assertDatabaseHas('estimates', ['id' => $estimate->id, 'status' => 'draft']);
        Bus::assertNotDispatched(DeliverPlatformMail::class);
    }

    public function test_sixty81_no_consent(): void
    {
        Bus::fake();
        customerMailIsPermitted();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        $person = Person::create(['business_id' => $biz->id, 'email' => 'est-8371-3@example.test']);
        $customer = Customer::factory()->create(['business_id' => $biz->id, 'email' => 'est-8371-3@example.test']);

        $estimate = app(EstimateDraftAction::class)->handle((int) $biz->id, $person->id, [
            ['service_name' => 'Gutter cleaning', 'quantity' => 1, 'unit_price_cents' => 5000],
        ]);

        Livewire::test(EstimatesList::class)
            ->call('sendEstimate', $estimate->id)
            ->assertSet('success', null);
        $component = Livewire::test(EstimatesList::class)->call('sendEstimate', $estimate->id);
        $this->assertStringContainsString('not allowed right now', $component->get('error'));

        $this->assertDatabaseHas('estimates', ['id' => $estimate->id, 'status' => 'draft']);
        Bus::assertNotDispatched(DeliverPlatformMail::class);
    }

    public function test_sixty81_staff_forbidden(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Staff]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        Livewire::test(EstimatesList::class)
            ->call('sendEstimate', 1)
            ->assertForbidden();
    }

    public function test_sixty81_second_send_duplicate(): void
    {
        Bus::fake();
        customerMailIsPermitted();

        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);
        app(CreditLedger::class)->record(
            CreditProduct::Email,
            CreditKind::Purchase,
            100,
            'system',
            'test',
            null,
            null
        );

        $person = Person::create(['business_id' => $biz->id, 'email' => 'est-8371-5@example.test']);
        $customer = Customer::factory()->create(['business_id' => $biz->id, 'email' => 'est-8371-5@example.test']);
        permitFor($customer);

        $estimate = app(EstimateDraftAction::class)->handle((int) $biz->id, $person->id, [
            ['service_name' => 'Gutter cleaning', 'quantity' => 1, 'unit_price_cents' => 5000],
        ]);

        Livewire::test(EstimatesList::class)
            ->call('sendEstimate', $estimate->id)
            ->assertSet('error', null);

        // second send
        Livewire::test(EstimatesList::class)
            ->call('sendEstimate', $estimate->id)
            ->assertSet('success', null)
            ->assertSet('error', 'Already emailed.');

        $this->assertDatabaseCount('outreach_messages', 1);
    }
}
