<?php
declare(strict_types=1);
namespace App\Modules\X08\Domain;

final class X08Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
