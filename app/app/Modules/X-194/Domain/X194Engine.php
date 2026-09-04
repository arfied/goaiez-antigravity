<?php
declare(strict_types=1);
namespace App\Modules\X194\Domain;

final class X194Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
