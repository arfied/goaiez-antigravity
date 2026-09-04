<?php

declare(strict_types=1);

namespace App\Modules\X198\Domain;

interface ProcessorAdapter
{
    public function beginKyc(int $businessId): string;
}
