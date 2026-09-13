<?php

declare(strict_types=1);

namespace Tests\Modules\X163;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CAgent\Events\AgentRefused;
use App\Modules\X163\Domain\PricebookEngine;
use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X163\Ui\DailyPricingDigest;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;

class DailyPricingDigestTest extends TestCase
{
    public function test_guest_is_forbidden(): void
    {
        Livewire::test(DailyPricingDigest::class)->assertForbidden();
    }

    public function test_renders_empty_state_when_no_refusals(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        Livewire::actingAs($owner)
            ->test(DailyPricingDigest::class)
            ->assertOk()
            ->assertSee('Every pricing question was answered')
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
            ->assertSee('1 refusal')
            ->assertSee('1 pricing question we could not answer');
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

        $event = new AgentRefused(
            $biz->id,
            'NO_FACT',
            'I do not know the pricebook rate for a drain unblock',
            'drain unblock'
        );
        Event::dispatch($event);

        Livewire::actingAs($owner)
            ->test(DailyPricingDigest::class)
            ->assertOk()
            ->assertSee('drain unblock')
            ->assertSee('1 refusal')
            ->assertSee('1 pricing question we could not answer');
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

    public function test_a_gap_from_an_earlier_day_is_still_in_the_digest(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $event = new AgentRefused(
            $biz->id,
            'NO_FACT',
            'I do not know the pricebook rate for a drain unblock',
            'drain unblock'
        );
        Event::dispatch($event);

        PriceBookItem::query()->update(['refusal_flagged_at' => now()->subDays(3)]);
        $expected = PriceBookItem::first()->refusal_flagged_at->format('j M H:i');

        Livewire::actingAs($owner)
            ->test(DailyPricingDigest::class)
            ->assertOk()
            ->assertSee('drain unblock')
            ->assertSee($expected);
    }

    public function test_pricing_a_gap_in_the_digest_confirms_it_and_clears_it(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $event = new AgentRefused(
            $biz->id,
            'NO_FACT',
            'I do not know the pricebook rate for a drain unblock',
            'drain unblock'
        );
        Event::dispatch($event);

        $item = PriceBookItem::first();

        Livewire::actingAs($owner)
            ->test(DailyPricingDigest::class)
            ->set('prices.'.$item->id, 125.00)
            ->call('confirm', $item->id);

        $this->assertDatabaseHas('price_book_items', [
            'id' => $item->id,
            'is_confirmed' => true,
            'price_cents' => 12500,
        ]);

        Livewire::actingAs($owner)
            ->test(DailyPricingDigest::class)
            ->assertDontSee('drain unblock');
    }

    public function test_a_gap_left_at_zero_stays_and_says_it_needs_a_price(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $event = new AgentRefused(
            $biz->id,
            'NO_FACT',
            'I do not know the pricebook rate for a drain unblock',
            'drain unblock'
        );
        Event::dispatch($event);

        $item = PriceBookItem::first();

        Livewire::actingAs($owner)
            ->test(DailyPricingDigest::class)
            ->call('confirm', $item->id)
            ->assertSee('Needs a price');

        $this->assertDatabaseHas('price_book_items', [
            'id' => $item->id,
            'is_confirmed' => false,
        ]);
    }

    public function test_it_sorts_items_by_refusal_count_desc_then_recency(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'High Refusals Service',
            'price_cents' => 0,
            'is_sample' => false,
            'is_confirmed' => false,
            'refusal_count' => 40,
            'refusal_flagged_at' => now()->subHour(),
        ]);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Recent Service',
            'price_cents' => 0,
            'is_sample' => false,
            'is_confirmed' => false,
            'refusal_count' => 1,
            'refusal_flagged_at' => now(),
        ]);

        Livewire::actingAs($owner)
            ->test(DailyPricingDigest::class)
            ->assertSeeInOrder(['High Refusals Service', 'Recent Service']);
    }

    public function test_it_renders_singular_text_when_only_one_refusal_exists(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Singular Service',
            'price_cents' => 0,
            'is_sample' => false,
            'is_confirmed' => false,
            'refusal_count' => 1,
            'refusal_flagged_at' => now(),
        ]);

        Livewire::actingAs($owner)
            ->test(DailyPricingDigest::class)
            ->assertSee('1 pricing question we could not answer')
            ->assertSee('1 refusal')
            ->assertDontSee('1 refusals')
            ->assertDontSee('1 pricing questions');
    }

    public function test_confirming_clears_the_prices_key_for_that_item_defect_arm(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Defect Arm',
            'price_cents' => 10000,
            'is_sample' => false,
            'is_confirmed' => false,
            'refusal_count' => 1,
            'refusal_flagged_at' => now(),
        ]);

        $component = Livewire::actingAs($owner)
            ->test(DailyPricingDigest::class)
            ->set('prices.'.$item->id, 125.00)
            ->call('confirm', $item->id);

        $this->assertArrayNotHasKey($item->id, $component->get('prices'), 'Defect arm prices leak');
    }

    public function test_confirming_leaves_other_prices_keys_alone_regression_arm(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $item1 = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Regression Arm 1',
            'price_cents' => 10000,
            'is_sample' => false,
            'is_confirmed' => false,
            'refusal_count' => 1,
            'refusal_flagged_at' => now(),
        ]);

        $item2 = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Regression Arm 2',
            'price_cents' => 20000,
            'is_sample' => false,
            'is_confirmed' => false,
            'refusal_count' => 1,
            'refusal_flagged_at' => now(),
        ]);

        $component = Livewire::actingAs($owner)
            ->test(DailyPricingDigest::class)
            ->set('prices.'.$item1->id, 100.00)
            ->set('prices.'.$item2->id, 125.00)
            ->call('confirm', $item1->id);

        $prices = array_filter($component->get('prices'), fn ($p) => $p == 125.0);
        $this->assertArrayHasKey($item2->id, $prices, 'Regression arm collateral wipe');
    }

    public function test_staff_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Staff;
        $user->save();
        TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(DailyPricingDigest::class)->assertForbidden();
    }

    public function test_manager_is_admitted(): void
    {
        $user = User::factory()->create();
        $user->role = UserRole::Manager;
        $user->save();
        TestCase::provisionTenant(['owner_user_id' => $user->id]);
        Tenancy::setUser($user->id);

        Livewire::actingAs($user)->test(DailyPricingDigest::class)->assertOk();
    }

    public function test_a_browser_call_cannot_rewrite_a_confirmed_price_on_the_daily_digest(): void
    {
        $owner = User::factory()->create();
        $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::setUser($owner->id);

        $item = PriceBookItem::create([
            'business_id' => $biz->id,
            'service_name' => 'Gutter Clean',
            'price_cents' => 9500,
            'is_sample' => false,
            'is_confirmed' => true,
            'confirmed_at' => now(),
        ]);

        $component = Livewire::actingAs($owner)->test(DailyPricingDigest::class);

        $refused = false;
        try {
            $component->call('updatePrice', $item->id, 0);
        } catch (MethodNotFoundException $e) {
            $refused = true;
        }

        $this->assertEquals(9500, $item->fresh()->price_cents, 'T2 A1 a browser call rewrote a confirmed price');
        $this->assertTrue($refused, 'T2 A2 updatePrice answered a browser call');
    }
}
