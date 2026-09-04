<?php

declare(strict_types=1);

namespace Tests\Modules\X163;

use App\Models\User;
use App\Modules\X163\Domain\PricebookEngine;
use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X163\Ui\DailyPricingDigest;
use App\Support\Tenancy;
use Livewire\Livewire;
use Tests\TestCase;

class DailyPricingDigestTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(DailyPricingDigest::class)->assertForbidden();
    }

    public function test_renders_empty_state_when_no_refusals_today(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(DailyPricingDigest::class)
            ->assertOk()
            ->assertSee('No pricing questions refused today.');
    }

    public function test_renders_refused_item(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Refused Service',
            'price_cents' => 10000,
            'is_sample' => true,
            'is_confirmed' => false,
        ]);

        $engine = new PricebookEngine;
        $engine->lookup($biz->id, 'Refused Service', 'customer');

        Livewire::actingAs($owner)
            ->test(DailyPricingDigest::class)
            ->assertOk()
            ->assertSee('Refused Service')
            ->assertSee('1 refusals')
            ->assertSee('Click to confirm');
    }
}
