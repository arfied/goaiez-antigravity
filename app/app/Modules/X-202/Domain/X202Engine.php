<?php
declare(strict_types=1);
namespace App\Modules\X202\Domain;

final class X202Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
