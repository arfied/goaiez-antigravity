<?php

declare(strict_types=1);

namespace App\Modules\X204\Actions;

use App\Modules\X204\Domain\ConsentService;
use App\Modules\X204\Models\ImportAttestation;

final class AttestationRecordAction
{
    public function __construct(private readonly ConsentService $service) {}

    public function handle(int $businessId, string $hash, string $source, int $count, string $attestedBy): ImportAttestation
    {
        return $this->service->recordAttestation($businessId, $hash, $source, $count, $attestedBy);
    }
}
