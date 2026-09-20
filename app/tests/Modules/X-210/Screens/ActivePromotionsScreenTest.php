<?php

declare(strict_types=1);

namespace Tests\Modules\X210\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X210\Actions\PromotionCreateAction;
use App\Modules\X210\Ui\ActivePromotions;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class ActivePromotionsScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-210.active-promotions'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('No offers running')
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
            maxRedemptions: 10,
        );
        Tenancy::forget();

        $this->get(route('x-210.active-promotions'))
            ->assertOk()
            ->assertSee('SPRING20')
            ->assertSee('20% off')
            ->assertSee('0 of 50 used')
            ->assertSee('FALL15')
            ->assertSee('$15.00 off')
            ->assertDontSee('No offers running');

        Livewire::test(ActivePromotions::class)->assertOk();
    }
}
