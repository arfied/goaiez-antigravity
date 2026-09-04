<?php
declare(strict_types=1);
namespace App\Modules\X145\Domain;

final class X145Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
