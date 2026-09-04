<?php
declare(strict_types=1);
namespace App\Modules\X104\Domain;

final class X104Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
