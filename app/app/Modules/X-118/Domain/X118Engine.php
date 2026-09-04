<?php
declare(strict_types=1);
namespace App\Modules\X118\Domain;

final class X118Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
