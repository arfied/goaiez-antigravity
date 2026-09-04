<?php
declare(strict_types=1);
namespace App\Modules\X177\Domain;

final class X177Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
