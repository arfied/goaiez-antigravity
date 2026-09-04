<?php
declare(strict_types=1);
namespace App\Modules\X142\Domain;

final class X142Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
