<?php
declare(strict_types=1);

namespace App\Modules\X155\Domain;

final class X155Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
