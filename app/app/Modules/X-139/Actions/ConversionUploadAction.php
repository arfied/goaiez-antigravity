<?php

declare(strict_types=1);

namespace App\Modules\X139\Actions;

use App\Modules\X139\Domain\ConversionUploadEngine;
use App\Services\Config\DefaultsRegistry;
use Carbon\Carbon;

final class ConversionUploadAction
{
    private function attributionWindowDays(): int
    {
        return $this->registry->int('attribution.conversion.window_days');
    }

    public function __construct(private readonly ConversionUploadEngine $engine, private DefaultsRegistry $registry) {}

    public function handle(
        int $businessId,
        int $jobId,
        int $conversionValueCents,
        Carbon $touchTimestamp,
        ?string $gclidOrFbc = null,
        ?int $attributionWindowDays = null
    ): array {
        $attributionWindowDays ??= $this->attributionWindowDays();

        return $this->engine->upload($businessId, $jobId, $conversionValueCents, $touchTimestamp, $gclidOrFbc, $attributionWindowDays);
    }
}
