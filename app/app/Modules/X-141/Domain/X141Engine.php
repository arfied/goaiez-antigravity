<?php
declare(strict_types=1);
namespace App\Modules\X141\Domain;

final class X141Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
