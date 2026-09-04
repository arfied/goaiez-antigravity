<?php
declare(strict_types=1);
namespace App\Modules\X200\Domain;

final class X200Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
