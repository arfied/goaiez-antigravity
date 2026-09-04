<?php
declare(strict_types=1);
namespace App\Modules\X138\Domain;

final class X138Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
