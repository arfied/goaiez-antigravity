<?php

declare(strict_types=1);

namespace Tests\Modules\X210\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X210\Models\Promotion;
use App\Modules\X210\Models\PromotionRedemption;
use App\Modules\X210\Ui\EarnedVsGivenPanel;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class EarnedVsGivenPanelScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-210.earnedvsgiven-panel'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertDontSee('this screen is planned in')
            ->assertSee('No discounts given yet.');

        Tenancy::set((int) $biz->id);
        $promo = Promotion::create(['business_id' => $biz->id, 'code' => 'DISTINCT4472', 'discount_type' => 'percentage', 'discount_value' => 10]);
        PromotionRedemption::create(['business_id' => $biz->id, 'promotion_id' => $promo->id, 'customer_id' => 1, 'order_id' => 'order-4472', 'discount_applied_cents' => 123456, 'redeemed_at' => now()]);
        Tenancy::forget();

        $this->get(route('x-210.earnedvsgiven-panel'))
            ->assertOk()
            ->assertSee('1,234.56')
            ->assertDontSee('No discounts given yet.');

        Livewire::actingAs($owner)->test(EarnedVsGivenPanel::class, ['businessId' => $biz->id])->assertOk();
    }

    public function test_redeem_promotion_control(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        // Drive PromotionBuilder to create an offer
        Livewire::actingAs($owner)->test(\App\Modules\X210\Ui\PromotionBuilder::class)
            ->set('code', 'TESTDISCOUNT')
            ->set('discountType', 'percentage')
            ->set('amount', 25)
            ->set('maxUses', 10)
            ->set('measurementWindowDays', 7)
            ->call('save')
            ->assertSet('code', ''); // assuming it resets or saves successfully

        // Refusal case: empty input
        Livewire::actingAs($owner)->test(EarnedVsGivenPanel::class, ['businessId' => $biz->id])
            ->set('code', '')
            ->set('customerId', '999')
            ->set('orderId', 'ORD-EMPTY')
            ->set('orderAmountCents', '10000')
            ->call('redeem')
            ->assertSet('error', 'Code is required.')
            ->assertSet('success', '');

        $this->assertDatabaseMissing((new PromotionRedemption)->getTable(), [
            'order_id' => 'ORD-EMPTY',
        ]);

        // Refusal case: invalid code
        Livewire::actingAs($owner)->test(EarnedVsGivenPanel::class, ['businessId' => $biz->id])
            ->set('code', 'INVALID')
            ->set('customerId', '999')
            ->set('orderId', 'ORD-INV')
            ->set('orderAmountCents', '10000')
            ->call('redeem')
            ->assertSet('error', 'No query results for model [App\Modules\X210\Models\Promotion].')
            ->assertSet('success', '');

        $this->assertDatabaseMissing((new PromotionRedemption)->getTable(), [
            'order_id' => 'ORD-INV',
        ]);

        // Success case
        Livewire::actingAs($owner)->test(EarnedVsGivenPanel::class, ['businessId' => $biz->id])
            ->set('code', 'TESTDISCOUNT')
            ->set('customerId', '123')
            ->set('orderId', 'ORD-1234')
            ->set('orderAmountCents', '10000')
            ->call('redeem')
            ->assertSet('error', '')
            ->assertSet('success', 'Promotion applied. Discount computed: 2500 cents. This feeds the margin lists; nothing downstream is wired to it yet.');

        $this->assertDatabaseHas((new PromotionRedemption)->getTable(), [
            'order_id' => 'ORD-1234',
            'discount_applied_cents' => 2500,
            'customer_id' => 123,
        ]);

        Tenancy::forget();

        // Control route asserting new value
        $this->get(route('x-210.earnedvsgiven-panel'))
            ->assertOk()
            ->assertSee('25.00')
            ->assertDontSee('No discounts given yet.');

        // Fan-out route
        $this->get(route('x-210.redemptions'))
            ->assertOk()
            ->assertDontSee('No offers used yet');
    }
}
