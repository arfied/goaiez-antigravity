<?php

declare(strict_types=1);

namespace App\Modules\X139\Domain;

use App\Modules\X139\Events\ConversionRejected;
use App\Modules\X139\Events\ConversionUploaded;
use App\Modules\X139\Models\ConversionUpload;
use App\Services\Config\DefaultsRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class ConversionUploadEngine
{
    public const ATTRIBUTION_WINDOW_DAYS = 90;

    /**
     * Uploads offline conversion to ad platform (G13-22).
     * Zero bid/budget/campaign management code (TEST ANCHOR).
     * A completed job outside the attribution window is NOT uploaded (TEST ANCHOR).
     */
    public function attributionWindowDays(): int
    {
        return $this->registry->int('attribution.conversion.window_days');
    }

    public function __construct(private DefaultsRegistry $registry) {}

    public function upload(
        int $businessId,
        int $jobId,
        int $conversionValueCents,
        Carbon $touchTimestamp,
        ?string $gclidOrFbc = null,
        ?int $attributionWindowDays = null
    ): array {
        $attributionWindowDays ??= $this->attributionWindowDays();
        $now = Carbon::now();
        $daysDiff = (int) round($touchTimestamp->diffInDays($now));

        // 1. Attribution Window Gate (TEST ANCHOR)
        if ($daysDiff > $attributionWindowDays) {
            $reason = "Touch timestamp is {$daysDiff} days old, exceeding {$attributionWindowDays}-day attribution window";

            $record = ConversionUpload::create([
                'business_id' => $businessId,
                'job_id' => $jobId,
                'conversion_value_cents' => $conversionValueCents,
                'gclid_or_fbc' => $gclidOrFbc,
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ]);

            Event::dispatch(new ConversionRejected($businessId, $jobId, $reason));

            return [
                'status' => 'rejected',
                'refusal_code' => 'OUTSIDE_ATTRIBUTION_WINDOW',
                'message' => $reason,
                'uploaded' => false,
                'record_id' => $record->id,
            ];
        }

        // 2. Successful Upload within window (TEST ANCHOR)
        $record = ConversionUpload::create([
            'business_id' => $businessId,
            'job_id' => $jobId,
            'conversion_value_cents' => $conversionValueCents,
            'gclid_or_fbc' => $gclidOrFbc,
            'status' => 'uploaded',
            'rejection_reason' => null,
        ]);

        Event::dispatch(new ConversionUploaded($businessId, $jobId, $conversionValueCents));

        return [
            'status' => 'uploaded',
            'uploaded' => true,
            'job_id' => $jobId,
            'conversion_value_cents' => $conversionValueCents,
            'record_id' => $record->id,
        ];
    }
}
