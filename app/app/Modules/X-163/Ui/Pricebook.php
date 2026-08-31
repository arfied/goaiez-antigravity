<?php

declare(strict_types=1);

namespace App\Modules\X163\Ui;

use App\Modules\X163\Actions\BookVersionAction;
use App\Modules\X163\Actions\PriceConfirmAction;
use App\Modules\X163\Actions\PriceLookupAction;
use App\Modules\X163\Domain\PricebookEngine;
use App\Modules\X163\Models\CalloutFee;
use App\Modules\X163\Models\LocationBook;
use App\Modules\X163\Models\PriceBookItem;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Pricebook extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $activeCategory = 'all';

    // Callout Fee Configuration
    public float $calloutFeeDollars = 85.00;

    public bool $deductedIfProceeding = true;

    public string $calloutExplanation = 'Our diagnostic callout fee is $85.00, which is fully credited toward your repair if you approve the job.';

    // New Item Form
    public string $newServiceName = '';

    public string $newCategory = 'repairs';

    public float $newPriceDollars = 150.00;

    public float $newTaxRatePct = 8.25;

    public bool $newIsSample = false;

    // AI Quoting Simulator
    public string $testQuery = 'Emergency Leak Repair';

    public string $testChannel = 'customer'; // customer vs admin

    public ?array $testQuoteResult = null;

    // Flash Notice
    public ?string $actionNotice = null;

    public string $noticeType = 'success';

    public function mount(): void
    {
        $tenantId = Tenancy::id() ?: 0;
        if ($tenantId <= 0) {
            abort(403, 'Tenant context is required to access pricebook');
        }

        $this->businessId = (int) $tenantId;
        DB::statement("SET app.business_id = '{$this->businessId}'");

        // Seed initial items if empty
        if (PriceBookItem::where('business_id', $this->businessId)->count() === 0) {
            PriceBookItem::create([
                'business_id' => $this->businessId,
                'service_name' => 'Standard Diagnostic & Inspection',
                'price_cents' => 8500,
                'is_sample' => false,
                'is_confirmed' => true,
                'tax_rate_pct' => 8.25,
            ]);
            PriceBookItem::create([
                'business_id' => $this->businessId,
                'service_name' => 'Emergency Leak Repair & Coupling',
                'price_cents' => 19500,
                'is_sample' => false,
                'is_confirmed' => true,
                'tax_rate_pct' => 8.25,
            ]);
            PriceBookItem::create([
                'business_id' => $this->businessId,
                'service_name' => 'Main Drain Hydro-Jetting & Rooter',
                'price_cents' => 35000,
                'is_sample' => false,
                'is_confirmed' => true,
                'tax_rate_pct' => 8.25,
            ]);
            PriceBookItem::create([
                'business_id' => $this->businessId,
                'service_name' => 'Sample Tankless Water Heater Install',
                'price_cents' => 185000,
                'is_sample' => true,
                'is_confirmed' => false,
                'tax_rate_pct' => 8.25,
            ]);
        }

        // Seed or load Callout Fee
        $fee = CalloutFee::where('business_id', $this->businessId)->first();
        if ($fee) {
            $this->calloutFeeDollars = $fee->callout_fee_cents / 100;
            $this->deductedIfProceeding = (bool) $fee->deducted_if_proceeding;
            $this->calloutExplanation = (string) ($fee->explanation_text ?? $this->calloutExplanation);
        } else {
            CalloutFee::create([
                'business_id' => $this->businessId,
                'callout_fee_cents' => 8500,
                'deducted_if_proceeding' => true,
                'explanation_text' => $this->calloutExplanation,
            ]);
        }

        // Seed initial locations if empty
        if (LocationBook::where('business_id', $this->businessId)->count() === 0) {
            LocationBook::create(['business_id' => $this->businessId, 'location_name' => 'Austin Central', 'version' => 1]);
            LocationBook::create(['business_id' => $this->businessId, 'location_name' => 'North Austin / Round Rock', 'version' => 1]);
        }
    }

    public function saveCalloutFee(): void
    {
        DB::statement("SET app.business_id = '{$this->businessId}'");
        $fee = CalloutFee::where('business_id', $this->businessId)->first();
        $cents = (int) round($this->calloutFeeDollars * 100);

        if ($fee) {
            $fee->update([
                'callout_fee_cents' => $cents,
                'deducted_if_proceeding' => $this->deductedIfProceeding,
                'explanation_text' => $this->calloutExplanation,
            ]);
        } else {
            CalloutFee::create([
                'business_id' => $this->businessId,
                'callout_fee_cents' => $cents,
                'deducted_if_proceeding' => $this->deductedIfProceeding,
                'explanation_text' => $this->calloutExplanation,
            ]);
        }

        $this->noticeType = 'success';
        $this->actionNotice = '✅ Diagnostic callout fee updated. Ava will cite this verbatim to callers.';
    }

    public function addItem(): void
    {
        if (empty($this->newServiceName)) {
            return;
        }

        DB::statement("SET app.business_id = '{$this->businessId}'");
        PriceBookItem::create([
            'business_id' => $this->businessId,
            'service_name' => $this->newServiceName,
            'price_cents' => (int) round($this->newPriceDollars * 100),
            'tax_rate_pct' => $this->newTaxRatePct,
            'is_sample' => $this->newIsSample,
            'is_confirmed' => ! $this->newIsSample,
        ]);

        $this->newServiceName = '';
        $this->newPriceDollars = 150.00;
        $this->noticeType = 'success';
        $this->actionNotice = '✅ New service item added to pricebook.';
    }

    public function confirmItem(int $id): void
    {
        DB::statement("SET app.business_id = '{$this->businessId}'");
        $confirmer = app(PriceConfirmAction::class);
        $confirmer->handle($this->businessId, $id);

        $this->noticeType = 'success';
        $this->actionNotice = "🎉 Item #{$id} confirmed! It is now live and quotable by the voice AI agent.";
    }

    public function deleteItem(int $id): void
    {
        DB::statement("SET app.business_id = '{$this->businessId}'");
        PriceBookItem::where('business_id', $this->businessId)->where('id', $id)->delete();

        $this->noticeType = 'warning';
        $this->actionNotice = "🗑️ Item #{$id} removed from pricebook.";
    }

    public function bumpVersion(string $locationName): void
    {
        DB::statement("SET app.business_id = '{$this->businessId}'");
        $action = new BookVersionAction(new PricebookEngine);
        $res = $action->handle($this->businessId, $locationName);

        $this->noticeType = 'success';
        $this->actionNotice = "🚀 Version bumped for '{$locationName}' to Version {$res['version']}. Other location books remain untouched.";
    }

    public function runTestQuote(): void
    {
        DB::statement("SET app.business_id = '{$this->businessId}'");
        $lookup = new PriceLookupAction(new PricebookEngine);
        $res = $lookup->handle($this->businessId, $this->testQuery, $this->testChannel);

        $this->testQuoteResult = $res;
    }

    public function render()
    {
        DB::statement("SET app.business_id = '{$this->businessId}'");
        $items = PriceBookItem::where('business_id', $this->businessId)->orderBy('id', 'desc')->get();
        $locations = LocationBook::where('business_id', $this->businessId)->get();

        return view('x-163::pricebook', [
            'items' => $items,
            'locations' => $locations,
        ]);
    }
}
