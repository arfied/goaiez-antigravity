<?php
declare(strict_types=1);
namespace App\Modules\X173\Domain;

final class X173Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
