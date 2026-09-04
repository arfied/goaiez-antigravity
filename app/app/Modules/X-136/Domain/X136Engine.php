<?php
declare(strict_types=1);
namespace App\Modules\X136\Domain;

final class X136Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
