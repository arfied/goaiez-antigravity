<?php
declare(strict_types=1);
namespace App\Modules\X112\Domain;

final class X112Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
