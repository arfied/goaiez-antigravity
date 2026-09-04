<?php
declare(strict_types=1);
namespace App\Modules\X193\Domain;

final class X193Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
