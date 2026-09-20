<?php

declare(strict_types=1);

namespace Tests\Modules\X210\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X210\Actions\PromotionApplyAction;
use App\Modules\X210\Actions\PromotionCreateAction;
use App\Modules\X210\Ui\RedemptionsList;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class RedemptionsListScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-210.redemptions'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No offers used yet')
            ->assertDontSee('taken off in all')
            ->assertDontSee('this screen is planned in');

        Tenancy::set((int) $biz->id);
        app(PromotionCreateAction::class)->createPromotion(
            businessId: (int) $biz->id,
            code: 'SPRING20',
            discountValue: 20,
            discountType: 'percentage',
            maxRedemptions: 50, measurementWindowDays: 14,
        );
        app(PromotionCreateAction::class)->createPromotion(
            businessId: (int) $biz->id,
            code: 'FALL15',
            discountValue: 1500,
            discountType: 'fixed_cents',
            maxRedemptions: 10, measurementWindowDays: 14,
        );
        app(PromotionApplyAction::class)->applyPromotion((int) $biz->id, 'SPRING20', 8801, 'ORD-1001', 25000);
        app(PromotionApplyAction::class)->applyPromotion((int) $biz->id, 'FALL15', 8802, 'ORD-1002', 9000);
        Tenancy::forget();

        $this->get(route('x-210.redemptions'))
            ->assertOk()
            ->assertSee('SPRING20')
            ->assertSee('$50.00 off')
            ->assertSee('FALL15')
            ->assertSee('$15.00 off')
            ->assertSee('$65.00 taken off in all, across 2 uses')
            ->assertDontSee('No offers used yet');

        Livewire::test(RedemptionsList::class)->assertOk();
    }
}
