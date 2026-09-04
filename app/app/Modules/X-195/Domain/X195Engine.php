<?php
declare(strict_types=1);
namespace App\Modules\X195\Domain;

final class X195Engine
{
    public function enforceCapabilities(): void
    {
        throw new \InvalidArgumentException("REFUSES: Domain constraints enforced.");
    }
}
