<?php
declare(strict_types=1);
namespace App\Modules\X07\Domain;

final class X07Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
