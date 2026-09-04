<?php
declare(strict_types=1);
namespace App\Modules\X130\Domain;

final class X130Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
