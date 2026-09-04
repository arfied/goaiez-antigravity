<?php
declare(strict_types=1);
namespace App\Modules\X154\Domain;

final class X154Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
