<?php
declare(strict_types=1);
namespace App\Modules\X178\Domain;

final class X178Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
