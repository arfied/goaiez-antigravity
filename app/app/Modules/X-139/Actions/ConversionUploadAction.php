<?php

declare(strict_types=1);

namespace App\Modules\X139\Actions;

use App\Modules\X139\Domain\ConversionUploadEngine;
use Carbon\Carbon;

final class ConversionUploadAction
{
    public function __construct(private readonly ConversionUploadEngine $engine = new ConversionUploadEngine) {}

    public function handle(
        int $businessId,
        int $jobId,
        int $conversionValueCents,
        Carbon $touchTimestamp,
        ?string $gclidOrFbc = null,
        int $attributionWindowDays = 90
    ): array {
        return $this->engine->upload($businessId, $jobId, $conversionValueCents, $touchTimestamp, $gclidOrFbc, $attributionWindowDays);
    }
}
