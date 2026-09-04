<?php
declare(strict_types=1);
namespace App\Modules\X105\Domain;

final class X105Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
