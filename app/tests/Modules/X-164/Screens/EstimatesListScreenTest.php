<?php

declare(strict_types=1);

namespace Tests\Modules\X164\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X164\Actions\EstimateDraftAction;
use App\Modules\X164\Models\Estimate;
use App\Modules\X164\Models\EstimateLine;
use App\Modules\X164\Models\EstimateVersion;
use App\Modules\X164\Ui\EstimatesList;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

        Tenancy::set((int) $biz->id);

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

        Tenancy::set((int) $biz->id);

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

        Tenancy::set((int) $biz->id);

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

        Tenancy::set((int) $biz->id);

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
            ->assertSet('success', 'Estimate '.$estimateNumber.' is marked sent. Nothing is delivered '
                .'to the customer yet — this records the status only.');

        $estimateTable = (new Estimate)->getTable();
        $this->assertDatabaseHas($estimateTable, ['id' => $id, 'status' => 'sent', 'business_id' => $biz->id]);
    }

    public function test_estimates_list_shows_a_sent_estimate(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::set((int) $biz->id);

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Gutter cleaning')
            ->set('quantity', 1)
            ->set('unitPriceCents', 5000)
            ->call('draftEstimate');

        $est = Estimate::where('business_id', $biz->id)->first();
        $id = $est->id;

        Livewire::test(EstimatesList::class)
            ->call('sendEstimate', $id);

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

        Tenancy::set((int) $bizB->id);

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Gutter cleaning')
            ->set('quantity', 1)
            ->set('unitPriceCents', 5000)
            ->call('draftEstimate');

        $estB = Estimate::where('business_id', $bizB->id)->first();
        $bEstimateId = $estB->id;

        Tenancy::set((int) $bizA->id);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(EstimatesList::class)->call('sendEstimate', $bEstimateId);
    }

    public function test_can_accept_a_sent_estimate(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set((int) $biz->id);

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Culvert Relining 8309')
            ->set('quantity', 1)
            ->set('unitPriceCents', 830900)
            ->call('draftEstimate');

        $est = Estimate::where('business_id', $biz->id)->first();
        Livewire::test(EstimatesList::class)->call('sendEstimate', $est->id);

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

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Culvert Relining 8309')
            ->set('quantity', 1)
            ->set('unitPriceCents', 830900)
            ->call('draftEstimate');

        $est = Estimate::where('business_id', $biz->id)->first();
        Livewire::test(EstimatesList::class)->call('sendEstimate', $est->id);

        Tenancy::forget();
        $this->get(route('x-164.estimates-list'))
            ->assertOk()
            ->assertSee('Sent')
            ->assertSee('acceptEstimate(');

        Tenancy::set((int) $biz->id);
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

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Culvert Relining 8309')
            ->set('quantity', 1)
            ->set('unitPriceCents', 830900)
            ->call('draftEstimate');

        $est = Estimate::where('business_id', $biz->id)->first();
        Livewire::test(EstimatesList::class)->call('sendEstimate', $est->id);

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

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Culvert Relining 8309')
            ->set('quantity', 1)
            ->set('unitPriceCents', 830900)
            ->call('draftEstimate');

        $est = Estimate::where('business_id', $biz->id)->first();
        Livewire::test(EstimatesList::class)->call('sendEstimate', $est->id);

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

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Culvert Relining 8309')
            ->set('quantity', 1)
            ->set('unitPriceCents', 830900)
            ->call('draftEstimate');

        $est = Estimate::where('business_id', $biz->id)->first();
        Livewire::test(EstimatesList::class)->call('sendEstimate', $est->id);

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
        Tenancy::set((int) $bizB->id);
        $this->actingAs($ownerB);

        Livewire::test(EstimatesList::class)
            ->set('serviceName', 'Culvert Relining 8309')
            ->set('quantity', 1)
            ->set('unitPriceCents', 830900)
            ->call('draftEstimate');

        $estB = Estimate::where('business_id', $bizB->id)->first();
        Livewire::test(EstimatesList::class)->call('sendEstimate', $estB->id);

        Tenancy::setUser($ownerA->id);
        Tenancy::set((int) $bizA->id);
        $this->actingAs($ownerA);

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(EstimatesList::class)->set('customerSignature', 'Marisol Quintero')->call('acceptEstimate', $estB->id);
    }
}
