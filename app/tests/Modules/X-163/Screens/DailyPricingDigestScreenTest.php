<?php

declare(strict_types=1);

namespace Tests\Modules\X163\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X163\Ui\DailyPricingDigest;
use Livewire\Livewire;
use Tests\TestCase;

class DailyPricingDigestScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Test Service P182',
            'price_cents' => 0,
            'is_confirmed' => false,
            'refusal_count' => 1,
            'refusal_flagged_at' => now(),
        ]);

        $response = $this->get(route('x-163.daily-pricing-digest'));
        $response->assertOk();
        $response->assertSee('Your account');
        $response->assertSee('Test Service P182');
        $response->assertDontSee('this screen is planned in');

        Livewire::test(DailyPricingDigest::class)->assertOk();
    }

    public function test_tenant_cannot_see_others_items(): void
    {
        $owner1 = User::factory()->create(['role' => UserRole::Owner]);
        $biz1 = $this->provisionTenant(['owner_user_id' => $owner1->id]);

        $owner2 = User::factory()->create(['role' => UserRole::Owner]);
        $biz2 = $this->provisionTenant(['owner_user_id' => $owner2->id]);

        // Insert as owner2
        $this->actingAs($owner2);
        PriceBookItem::create([
            'business_id' => $biz2->id,
            'service_name' => 'Secret Service P182',
            'price_cents' => 0,
            'is_confirmed' => false,
            'refusal_count' => 1,
            'refusal_flagged_at' => now(),
        ]);

        // Act as owner1
        $this->actingAs($owner1);
        $response = $this->get(route('x-163.daily-pricing-digest'));
        $response->assertDontSee('Secret Service P182');
    }

    public function test_does_not_show_unrefused_items(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Normal Service P182',
            'price_cents' => 1000,
            'is_confirmed' => false,
            'refusal_count' => 0,
            'refusal_flagged_at' => null,
        ]);

        $response = $this->get(route('x-163.daily-pricing-digest'));
        $response->assertDontSee('Normal Service P182');
    }
}
