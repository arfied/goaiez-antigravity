<?php
declare(strict_types=1);
namespace App\Modules\X140\Domain;

final class X140Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
