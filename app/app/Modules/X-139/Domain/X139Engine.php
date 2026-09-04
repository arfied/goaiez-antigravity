<?php
declare(strict_types=1);
namespace App\Modules\X139\Domain;

final class X139Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
