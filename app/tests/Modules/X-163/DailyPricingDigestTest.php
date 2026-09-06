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
            ->assertSee('Every pricing question today was answered')
            ->assertDontSee('View Pricebook');
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
            ->assertSee('1 pricing questions we could not answer today');
    }

    public function test_confirming_item_removes_it_from_digest(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Confirmable Service',
            'price_cents' => 15000,
            'is_sample' => true,
            'is_confirmed' => false,
        ]);

        $engine = new PricebookEngine;
        $engine->lookup($biz->id, 'Confirmable Service', 'customer');

        Livewire::actingAs($owner)
            ->test(DailyPricingDigest::class)
            ->assertSee('Confirmable Service')
            ->call('confirm', $item->id)
            ->assertDontSee('Confirmable Service');

        $this->assertDatabaseHas('price_book_items', [
            'id' => $item->id,
            'is_confirmed' => true,
        ]);
    }

    public function test_seeded_row_reaches_the_page(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Digest Seam',
            'price_cents' => 33488,
            'is_sample' => false,
            'is_confirmed' => false,
            'refusal_count' => 477,
            'refusal_flagged_at' => now(),
        ]);

        $this->actingAs($owner);
        $this->get(route('x-163.daily-pricing-digest'))->assertOk()->assertSee('334.88');
    }

    public function test_an_agent_price_gap_appears_in_the_owners_digest(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $event = new \App\Modules\CAgent\Events\AgentRefused(
            $biz->id,
            'NO_FACT',
            'I do not know the pricebook rate for a drain unblock',
            'drain unblock'
        );
        \Illuminate\Support\Facades\Event::dispatch($event);

        Livewire::actingAs($owner)
            ->test(DailyPricingDigest::class)
            ->assertOk()
            ->assertSee('drain unblock')
            ->assertSee('1 refusals')
            ->assertSee('1 pricing questions we could not answer today');
    }

    public function test_confirming_a_price_with_no_amount_is_refused_and_says_so(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Zero Price Service',
            'price_cents' => 0,
            'is_sample' => false,
            'is_confirmed' => false,
            'refusal_count' => 1,
            'refusal_flagged_at' => now(),
        ]);

        Livewire::actingAs($owner)
            ->test(DailyPricingDigest::class)
            ->assertSee('Zero Price Service')
            ->call('confirm', $item->id)
            ->assertSee('Needs a price');

        $this->assertDatabaseHas('price_book_items', [
            'id' => $item->id,
            'is_confirmed' => false,
        ]);
    }
}
