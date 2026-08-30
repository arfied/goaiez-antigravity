<?php

declare(strict_types=1);

namespace App\Modules\X171\Actions;

use App\Modules\X171\Events\SignatureCaptured;
use Illuminate\Support\Facades\Event;

final class JobSignAction
{
    public function handle(int $businessId, int $jobId, string $signatureData): array
    {
        Event::dispatch(new SignatureCaptured($businessId, $jobId, $signatureData));

        return ['status' => 'signature_captured', 'signature' => $signatureData];
    }
}
