<?php
declare(strict_types=1);
namespace App\Modules\X197\Domain;

final class X197Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
