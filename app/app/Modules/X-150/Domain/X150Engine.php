<?php
declare(strict_types=1);
namespace App\Modules\X150\Domain;

final class X150Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
