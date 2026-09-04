<?php
declare(strict_types=1);
namespace App\Modules\X185\Domain;

final class X185Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
