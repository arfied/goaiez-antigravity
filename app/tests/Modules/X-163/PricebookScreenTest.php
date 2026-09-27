<?php

declare(strict_types=1);

namespace Tests\Modules\X163;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X163\Actions\CalloutLookupAction;
use App\Modules\X163\Actions\PriceQuoteAction;
use App\Modules\X163\Domain\PricebookEngine;
use App\Modules\X163\Models\CalloutFee;
use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X163\Ui\Pricebook;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class PricebookScreenTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(Pricebook::class)->assertForbidden();
    }

    public function test_fresh_tenant_renders_zero_rows(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->assertOk()
            ->assertSee('Add your first price');

        $this->assertEquals(0, PriceBookItem::where('business_id', $biz->id)->count());
    }

    public function test_add_item_stores_cents(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('newServiceName', 'Test Service')
            ->set('newPriceDollars', 150)
            ->call('addItem');

        $this->assertDatabaseHas('price_book_items', [
            'business_id' => $biz->id,
            'service_name' => 'Test Service',
            'price_cents' => 15000,
        ]);
    }

    public function test_sample_row_shows_pill(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Sample Service',
            'price_cents' => 10000,
            'is_sample' => true,
            'is_confirmed' => false,
            'tax_rate_pct' => 0.0,
        ]);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->assertSee('Sample Service')
            ->assertSeeHtml('<span>Sample</span>');
    }

    public function test_seeded_row_reaches_the_page(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Pricebook Seam',
            'price_cents' => 94225,
            'is_sample' => false,
            'is_confirmed' => false,
        ]);

        $this->actingAs($owner);
        $this->get(route('x-163.pricebook'))->assertOk()->assertSee('942.25');
    }

    public function test_inline_edit_unconfirms_a_confirmed_row(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $row = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Confirm Test 1',
            'price_cents' => 10000,
            'is_sample' => false,
            'is_confirmed' => true,
            'confirmed_at' => now()->subDay(),
            'tax_rate_pct' => 0.0,
        ]);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('inlinePrices.'.$row->id, 200.00)
            ->call('updatePrice', $row->id);

        $reloaded = $row->fresh();
        $this->assertFalse($reloaded->is_confirmed);
        $this->assertNull($reloaded->confirmed_at);
    }

    public function test_inline_edit_with_the_same_amount_leaves_confirmation_alone(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $row = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Confirm Test 2',
            'price_cents' => 30000,
            'is_sample' => false,
            'is_confirmed' => true,
            'confirmed_at' => now()->subDay(),
            'tax_rate_pct' => 0.0,
        ]);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('inlinePrices.'.$row->id, 300.00)
            ->call('updatePrice', $row->id);

        $reloaded = $row->fresh();
        $this->assertTrue($reloaded->is_confirmed);
        $this->assertEquals($row->confirmed_at, $reloaded->confirmed_at);
    }

    public function test_an_edited_price_is_no_longer_quoted_to_a_customer(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $row = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Confirm Test 3',
            'price_cents' => 40000,
            'is_sample' => false,
            'is_confirmed' => true,
            'confirmed_at' => now()->subDay(),
            'tax_rate_pct' => 0.0,
        ]);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('inlinePrices.'.$row->id, 500.00)
            ->call('updatePrice', $row->id);

        $engine = app(PricebookEngine::class);
        $result = $engine->lookup($biz->id, 'Confirm Test 3', 'customer');

        $this->assertEquals('refused', $result['status']);
    }

    public function test_add_item_refuses_a_duplicate_service_name(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Drain Clean',
            'price_cents' => 12500,
            'is_sample' => false,
            'is_confirmed' => true,
            'confirmed_at' => now()->subDay(),
            'tax_rate_pct' => 0.0,
        ]);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('newServiceName', 'Drain Clean')
            ->set('newPriceDollars', 999.00)
            ->call('addItem')
            ->assertHasErrors('newServiceName');

        $this->assertEquals(1, PriceBookItem::where('service_name', 'Drain Clean')->count());
    }

    public function test_add_item_allows_different_service_name_on_same_business(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Drain Clean',
            'price_cents' => 12500,
            'is_sample' => false,
            'is_confirmed' => true,
            'confirmed_at' => now()->subDay(),
            'tax_rate_pct' => 0.0,
        ]);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('newServiceName', 'Another Service')
            ->set('newPriceDollars', 999.00)
            ->call('addItem');

        $this->assertEquals(2, PriceBookItem::where('business_id', $biz->id)->count());
    }

    public function test_add_item_refusal_preserves_quoted_price_for_customer(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $existing = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Drain Clean',
            'price_cents' => 12500,
            'is_sample' => false,
            'is_confirmed' => true,
            'confirmed_at' => now()->subDay(),
            'tax_rate_pct' => 0.0,
        ]);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('newServiceName', 'Drain Clean')
            ->set('newPriceDollars', 999.00)
            ->call('addItem');

        $engine = app(PricebookEngine::class);
        $result = $engine->lookup($biz->id, 'Drain Clean', 'customer');

        $this->assertEquals($existing->fresh()->price_cents, $result['price_cents']);
    }

    public function test_duplicate_service_refusal_is_rendered_on_the_screen(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Drain Clean',
            'price_cents' => 12500,
            'is_sample' => false,
            'is_confirmed' => true,
            'confirmed_at' => now()->subDay(),
            'tax_rate_pct' => 0.0,
        ]);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('newServiceName', 'Drain Clean')
            ->set('newPriceDollars', 999.00)
            ->call('addItem')
            ->assertSee('A business-wide price for this service already exists.');
    }

    public function test_add_item_success_does_not_render_refusal_message(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Drain Clean',
            'price_cents' => 12500,
            'is_sample' => false,
            'is_confirmed' => true,
            'confirmed_at' => now()->subDay(),
            'tax_rate_pct' => 0.0,
        ]);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('newServiceName', 'Another Service')
            ->set('newPriceDollars', 999.00)
            ->call('addItem')
            ->assertDontSee('A business-wide price for this service already exists.');
    }

    public function test_staff_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(Pricebook::class)->assertForbidden();
    }

    public function test_manager_is_admitted(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Manager;
        $user->save();
        TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(Pricebook::class)->assertOk();
    }

    public function test_super_admin_is_admitted(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::SuperAdmin;
        $user->save();
        TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(Pricebook::class)->assertOk();
    }

    public function test_adding_a_zero_price_writes_the_row_unconfirmed_and_it_is_not_quoted(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $component = Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('newServiceName', 'Drain Unblock')
            ->set('newPriceDollars', 0)
            ->call('addItem');

        $row = PriceBookItem::where('business_id', $biz->id)->where('service_name', 'Drain Unblock')->first();
        $this->assertNotNull($row, 'T1 A1 row does not exist');
        $this->assertFalse($row->is_confirmed, 'T1 A2 is_confirmed is not false');
        $this->assertNull($row->confirmed_at, 'T1 A3 confirmed_at is not null');
        $this->assertArrayHasKey($row->id, $component->get('refusals'), 'T1 A4 refusals missing id');

        $quote = app(PriceQuoteAction::class)->handle($biz->id, 'how much for drain unblock');
        $this->assertArrayNotHasKey('amount', $quote, 'T1 A5 quote amount was present');
    }

    public function test_adding_a_negative_price_writes_the_row_unconfirmed(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('newServiceName', 'Gutter Clean')
            ->set('newPriceDollars', -50)
            ->call('addItem');

        $row = PriceBookItem::where('business_id', $biz->id)->where('service_name', 'Gutter Clean')->firstOrFail();
        $this->assertFalse($row->is_confirmed, 'T2 A1 is_confirmed is not false');
    }

    public function test_adding_a_positive_price_confirms_it_and_raises_no_flag(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $component = Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('newServiceName', 'Lawn Mow')
            ->set('newPriceDollars', 150)
            ->call('addItem');

        $row = PriceBookItem::where('business_id', $biz->id)->where('service_name', 'Lawn Mow')->firstOrFail();
        $this->assertTrue($row->is_confirmed, 'T3 A1 is_confirmed is not true');
        $this->assertArrayNotHasKey($row->id, $component->get('refusals'), 'T3 A2 refusals has id');
    }

    public function test_ticking_deducted_on_pricebook_saves_it_and_keeps_the_fee(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        CalloutFee::create(['business_id' => $biz->id, 'callout_fee_cents' => 8500, 'deducted_if_proceeding' => false]);

        Livewire::actingAs($owner)->test(Pricebook::class)->set('calloutFeeDeducted', true);

        $row = CalloutFee::where('business_id', $biz->id)->firstOrFail();
        $this->assertTrue($row->deducted_if_proceeding, 'T1 A1 deducted_if_proceeding is not true');
        $this->assertSame(8500, $row->callout_fee_cents, 'T1 A2 callout_fee_cents is not 8500');
    }

    public function test_pricebook_loads_a_stored_deduction_into_the_bound_property(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        CalloutFee::create(['business_id' => $biz->id, 'callout_fee_cents' => 8500, 'deducted_if_proceeding' => true]);

        $component = Livewire::actingAs($owner)->test(Pricebook::class);

        $this->assertSame(true, $component->get('calloutFeeDeducted'), 'T2 A1 calloutFeeDeducted is not true');
        $component->assertSeeHtml('wire:model="calloutFeeDeducted"');
    }

    public function test_ticking_deducted_on_pricebook_with_no_fee_writes_no_fee_and_the_agent_refuses(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)->test(Pricebook::class)->set('calloutFeeDeducted', true);

        $row = CalloutFee::where('business_id', $biz->id)->first();
        $this->assertNotNull($row, 'T3 A1 row is null');
        $this->assertSame(0, $row->callout_fee_cents, 'T3 A2 callout_fee_cents is not 0');
        $this->assertTrue($row->deducted_if_proceeding, 'T3 A3 deducted_if_proceeding is not true');

        $res = app(CalloutLookupAction::class)->handle($biz->id);
        $this->assertSame('NO_FACT', $res['refusal_code'] ?? null, 'T3 A4 refusal_code is not NO_FACT');
    }

    public function test_a_refused_add_keeps_confirm_clickable_and_typing_does_not_clear_the_flag(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $component = Livewire::actingAs($owner)
            ->test(Pricebook::class)
            ->set('newServiceName', 'Gutter Clean')
            ->set('newPriceDollars', 0)
            ->call('addItem');

        $row = PriceBookItem::where('business_id', $biz->id)->where('service_name', 'Gutter Clean')->firstOrFail();

        $component->assertSee('Needs a price');
        $this->assertDoesNotMatchRegularExpression('/wire:click="confirmItem\('.$row->id.'\)"\s+disabled/', $component->html(), 'T1 A2 Confirm is disabled on a row that needs a price');
        $component->set('inlinePrices.'.$row->id, 45.00);
        $this->assertArrayHasKey($row->id, $component->get('refusals'), 'T1 A3 typing a price cleared the flag before any confirm');
    }

    public function test_screen_renders_in_the_owner_shell_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-163.pricebook'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('Add your first price');

        Tenancy::set((int) $biz->id);
        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Drain Camera Inspection',
            'price_cents' => 18900,
            'is_sample' => false,
            'is_confirmed' => false,
            'tax_rate_pct' => 0.0,
        ]);
        Tenancy::forget();

        $this->get(route('x-163.pricebook'))
            ->assertOk()
            ->assertSee('Drain Camera Inspection')
            ->assertDontSee('Add your first price');
    }
}
