<?php
declare(strict_types=1);
namespace App\Modules\X117\Domain;

final class X117Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
