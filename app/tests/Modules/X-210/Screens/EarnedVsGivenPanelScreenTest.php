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
}
