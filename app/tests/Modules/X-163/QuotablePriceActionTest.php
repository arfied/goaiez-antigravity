<?php

declare(strict_types=1);

namespace Tests\Modules\X163;

use App\Modules\X163\Actions\QuotablePriceAction;
use App\Modules\X163\Models\PriceBookItem;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuotablePriceActionTest extends TestCase
{
    public function test_a_confirmed_item_resolves_to_its_amount_and_name(): void
    {
        $businessId = $this->tenant();
        $item = $this->item($businessId, 'Annual Tune-Up', 4999, false, true);

        $result = app(QuotablePriceAction::class)->resolve($businessId, $item->id);

        $this->assertSame(['amount' => 4999, 'service_name' => 'Annual Tune-Up'], $result, 'T1 A1 a confirmed item did not resolve to its amount and name');
    }

    public function test_an_unconfirmed_item_is_refused(): void
    {
        $businessId = $this->tenant();
        $item = $this->item($businessId, 'Brake Pads', 8000, false, false);

        $result = app(QuotablePriceAction::class)->resolve($businessId, $item->id);

        $this->assertSame(['refusal_code' => 'UNCONFIRMED'], $result, 'T2 A1 an unconfirmed item was not refused as UNCONFIRMED');
    }

    public function test_a_sample_item_is_refused(): void
    {
        $businessId = $this->tenant();
        $item = $this->item($businessId, 'Coolant Flush', 3000, true, false);

        $result = app(QuotablePriceAction::class)->resolve($businessId, $item->id);

        $this->assertSame(['refusal_code' => 'SAMPLE_STATE_REFUSED'], $result, 'T3 A1 a sample item was not refused as SAMPLE_STATE_REFUSED');
    }

    public function test_a_confirmed_zero_is_refused(): void
    {
        $businessId = $this->tenant();
        $item = $this->item($businessId, 'Drain Check', 0, false, true);

        $result = app(QuotablePriceAction::class)->resolve($businessId, $item->id);

        $this->assertSame(['refusal_code' => 'FILL_ME'], $result, 'T4 A1 a confirmed zero was not refused as FILL_ME');
    }

    public function test_an_unknown_item_is_refused(): void
    {
        $businessId = $this->tenant();

        $result = app(QuotablePriceAction::class)->resolve($businessId, 999999999);

        $this->assertSame(['refusal_code' => 'NO_FACT'], $result, 'T5 A1 an unknown item was not refused as NO_FACT');
    }

    public function test_options_list_only_quotable_items(): void
    {
        $businessId = $this->tenant();
        $tuneUp = $this->item($businessId, 'Annual Tune-Up', 4999, false, true);
        $this->item($businessId, 'Brake Pads', 8000, false, false);
        $this->item($businessId, 'Coolant Flush', 3000, true, true);
        $this->item($businessId, 'Drain Check', 0, false, true);

        $options = app(QuotablePriceAction::class)->options($businessId);

        $this->assertSame([['id' => $tuneUp->id, 'service_name' => 'Annual Tune-Up', 'price_cents' => 4999, 'price_max_cents' => null]], $options, 'T6 A1 options listed something other than the one quotable item');
    }

    private function tenant(): int
    {
        $biz = TestCase::provisionTenant(['name' => 'Quotable Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        return $biz->id;
    }

    private function item(int $businessId, string $name, int $cents, bool $sample, bool $confirmed): PriceBookItem
    {
        return PriceBookItem::create([
            'business_id' => $businessId,
            'service_name' => $name,
            'price_cents' => $cents,
            'is_sample' => $sample,
            'is_confirmed' => $confirmed,
        ]);
    }
}
