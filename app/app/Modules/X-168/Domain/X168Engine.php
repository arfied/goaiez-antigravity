<?php
declare(strict_types=1);
namespace App\Modules\X168\Domain;

final class X168Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
