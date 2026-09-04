<?php
declare(strict_types=1);
namespace App\Modules\X108\Domain;

final class X108Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
