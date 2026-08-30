<?php

declare(strict_types=1);

namespace App\Modules\X211\Actions;

use App\Modules\X211\Domain\ArEngine;

final class ArPackageForCollectionsAction
{
    public function __construct(private readonly ArEngine $engine) {}

    public function handle(int $businessId, int $invoiceId): array
    {
        return $this->engine->packageForCollections($businessId, $invoiceId);
    }
}
