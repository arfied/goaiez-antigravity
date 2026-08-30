<?php

declare(strict_types=1);

namespace App\Modules\X171\Actions;

use App\Modules\X171\Events\BarcodeScanned;
use Illuminate\Support\Facades\Event;

final class BarcodeScanAction
{
    public function handle(int $businessId, int $jobId, string $barcode): array
    {
        Event::dispatch(new BarcodeScanned($businessId, $jobId, $barcode));

        return ['status' => 'barcode_scanned', 'barcode' => $barcode];
    }
}
