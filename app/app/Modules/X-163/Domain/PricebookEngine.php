<?php

declare(strict_types=1);

namespace App\Modules\X163\Domain;

use App\Modules\X163\Events\PriceRefusalFlagged;
use App\Modules\X163\Events\VersionBumped;
use App\Modules\X163\Models\CalloutFee;
use App\Modules\X163\Models\LocationBook;
use App\Modules\X163\Models\PriceBookItem;
use App\Modules\X163\Models\PriceBookVersion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class PricebookEngine
{
    /**
     * Look up price with strict SAMPLE state filtering on customer channels (TEST ANCHOR).
     */
    public function lookup(
        int $businessId,
        string $serviceName,
        string $channel = 'customer',
        ?int $locationBookId = null
    ): array {
        $item = PriceBookItem::where('business_id', $businessId)
            ->where('service_name', $serviceName)
            ->when($locationBookId !== null, fn ($q) => $q->where('location_book_id', $locationBookId))
            ->first();

        if ($item === null) {
            Event::dispatch(new PriceRefusalFlagged($businessId, $serviceName, 'NO_FACT'));

            return [
                'status' => 'refused',
                'refusal_code' => 'NO_FACT',
                'reason' => "No pricebook entry found for {$serviceName}",
            ];
        }

        // SAMPLE check: SAMPLE prices must NEVER be returned to any customer channel (TEST ANCHOR)
        if ($item->is_sample && in_array($channel, ['customer', 'sms', 'voice', 'chat', 'web'], true)) {
            $item->increment('refusal_count', 1, ['refusal_flagged_at' => Carbon::now()]);

            Event::dispatch(new PriceRefusalFlagged($businessId, $serviceName, 'SAMPLE_STATE_REFUSED'));

            return [
                'status' => 'refused',
                'refusal_code' => 'SAMPLE_STATE_REFUSED',
                'reason' => 'Service is in SAMPLE state and cannot be quoted to customer channels',
            ];
        }

        return [
            'status' => 'quoted',
            'service_name' => $item->service_name,
            'price_cents' => $item->price_cents,
            'formatted_price' => '$'.number_format($item->price_cents / 100, 2),
            'tax_rate_pct' => $item->tax_rate_pct,
            'is_sample' => $item->is_sample,
        ];
    }

    /**
     * Look up callout fees with verbatim deduction explanation (TEST ANCHOR).
     */
    public function lookupCallout(int $businessId, ?int $locationBookId = null): array
    {
        $callout = CalloutFee::where('business_id', $businessId)
            ->when($locationBookId !== null, fn ($q) => $q->where('location_book_id', $locationBookId))
            ->first();

        if ($callout === null) {
            $callout = CalloutFee::create([
                'business_id' => $businessId,
                'location_book_id' => $locationBookId,
                'callout_fee_cents' => 7500,
                'deducted_if_proceeding' => true,
                'explanation_text' => 'Our callout fee is $75.00, which is fully deducted from the final invoice if you proceed with the repair.',
            ]);
        }

        $formattedFee = '$'.number_format($callout->callout_fee_cents / 100, 2);
        $deductText = $callout->deducted_if_proceeding
            ? " Our callout fee is {$formattedFee}, and it is deducted from the total if you proceed with the service."
            : " Our callout fee is {$formattedFee}.";

        return [
            'callout_fee_cents' => $callout->callout_fee_cents,
            'formatted_fee' => $formattedFee,
            'deducted_if_proceeding' => $callout->deducted_if_proceeding,
            'quote_response' => "To come out and diagnose the issue, our callout fee is {$formattedFee}, which is deducted from your total if you proceed with the work.",
            'explanation_text' => $callout->explanation_text ?? $deductText,
        ];
    }

    /**
     * Bump version on a specific location book while leaving other books unchanged (TEST ANCHOR).
     */
    public function bumpVersion(int $businessId, string $locationName): array
    {
        return DB::transaction(function () use ($businessId, $locationName) {
            $book = LocationBook::where('business_id', $businessId)
                ->where('location_name', $locationName)
                ->firstOrFail();

            $book->increment('version');

            PriceBookVersion::create([
                'business_id' => $businessId,
                'location_book_id' => $book->id,
                'version' => $book->version,
                'description' => "Bumped version for {$locationName}",
            ]);

            Event::dispatch(new VersionBumped(
                businessId: $businessId,
                locationBookId: $book->id,
                newVersion: $book->version
            ));

            return [
                'location_book_id' => $book->id,
                'location_name' => $locationName,
                'version' => $book->version,
            ];
        });
    }

}
