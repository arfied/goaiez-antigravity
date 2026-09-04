<?php
declare(strict_types=1);
namespace App\Modules\X01\Domain;

final class X01Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
